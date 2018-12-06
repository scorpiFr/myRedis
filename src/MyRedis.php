<?php

/**
 *
 * Permet de dialoguer avec un serveur Redis
 *
 * @Author : Camille Khalaghi
 *
 *
 *
 * Exemple de code :
 *
 * $r = new MyRedis('localhost', 6379)
 * $key = "testKey";
 * $r->setData($key, 'Hello world!');
 * print($r->getData($key)."\n"); // Hello world!

 * $r->setData($key, ['foo' => 'bar']);
 * print_r($r->getData($key)); // foo => bar
 *
 * $r->dropKey($key);
 */

class MyRedis
{
    /** @var resource $_redisConnexion */
    private $_redisConnexion;
    private $_isConnected = 0;

    // infos de connexion
    private $_hostName;
    private $_port;

    public function __construct($hostName, $port=6379)
    {
        $this->_hostName = $hostName;
        $this->_port = $port;
    }

    /**
     * Retourne toutes les cles
     * @return array  Liste des cles trouvees.
     * @throws \Exception si n'arrive pas a se connecter.
     */
    public function getKeys() {
        // verifs
        {
            if ($this->_isConnected == 0)
                $this->connect();
        }

        // envoi de la commande
        {
            $rawCommand = "*2\r\n$4\r\nKEYS\r\n$1\r\n*\r\n";
            fwrite($this->_redisConnexion, $rawCommand);
            unset($rawCommand);
        }

        // reponse
        {
            $rawResponse = fgets($this->_redisConnexion);
            $nbrKeys = substr($rawResponse, 1);
            $nbrKeys = intval($nbrKeys);
            unset($rawResponse);
        }

        // prise des cles
        {
            $res = array();
            for ($cpt = 0; $cpt < $nbrKeys; $cpt++) {
                $rawResponse = fgets($this->_redisConnexion);
                $keyLen = substr($rawResponse, 1);
                $keyLen = intval($keyLen) + 2;
                $tmp = fread($this->_redisConnexion, $keyLen);
                $keyName = trim($tmp);

                $res[$keyName] = $keyName;
                unset($keyLen, $keyName, $rawResponse, $tmp);
            }
        }
        // retour
        unset($nbrKeys, $cpt);
        return ($res);
    }

    /**
     * Verifie l'existance d'une cle
     * @param string $key
     * @return bool  true si la cle existe. false sinon.
     * @throws \Exception si n'arrive pas a se connecter.
     */
    public function isKeyExists($key) {
        // verifs
        {
            if (empty($key))
                return(true);
            if ($this->_isConnected == 0)
                $this->connect();
        }

        // preparation
        {
            $res = null;
            $keyLen = strlen($key);
            $rawCommand = "*2\r\n$6\r\nEXISTS\r\n$" . $keyLen . "\r\n" . $key . "\r\n";
        }

        // action
        fwrite($this->_redisConnexion, $rawCommand);

        // reponse
        {
            $rawResponse = fgets($this->_redisConnexion);
            $rawResponse2 = trim($rawResponse);
            $res = ($rawResponse2 == ':1') ? true : false;
            unset($rawResponse, $rawResponse2);
        }

        // retour
        unset($keyLen, $rawCommand);
        return ($res);
    }

    /**
     * Recupere une donnee
     * @param string $key
     * @return null|string|array  true si l'operation est reussie.
     * @throws \Exception si n'arrive pas a se connecter.
     */
    public function getData($key) {
        // verifs
        {
            if (empty($key))
                return (null);
            if ($this->_isConnected == 0)
                $this->connect();
        }

        // prise de l'enregistrement
        $result = $this->getStr($key);

        // interpretation
        if (is_null($result))
            $res = null;
        else if ($result[0] == '{') {
            $tmp = json_decode($result, true);
            $res = is_null($tmp) ? $result : $tmp;
            unset($tmp);
        } else if ($result == '[]') {
            $res = array();
        } else
            $res = $result;

        // retour
        unset($result);
        return ($res);
    }

    /**
     * Stoque un tableau
     * @param string $key
     * @param array|string|int $value
     * @return bool  true si l'operation est reussie.
     * @throws \Exception si n'arrive pas a se connecter.
     */
    public function setData($key, $value) {
        // verifs
        {
            if (empty($key))
                return (true);
            if ($this->_isConnected == 0)
                $this->connect();
        }

        if (is_null($value))
            $res = $this->dropKey($key);
        elseif (is_string($value))
            $res = $this->setStr($key, $value);
        elseif (is_int($value) || is_integer($value) || is_float($value))
            $res = $this->setStr($key, '' . $value);
        elseif (is_array($value)) {
            $tmp = json_encode($value);
            $res = $this->setStr($key, $tmp);
            unset($tmp);
        } else
            $res = false;

        // retour
        return ($res);
    }

    /**
     * Supprime une cle
     * @param string $key
     * @return bool  true si l'operation est reussie.
     * @throws \Exception si n'arrive pas a se connecter.
     */
    public function dropKey($key) {
        // verifs
        {
            if (empty($key))
                return(true);
            if ($this->_isConnected == 0)
                $this->connect();
        }

        // preparation
        {
            $res = null;
            $keyLen = strlen($key);
            $rawCommand = "*2\r\n$3\r\nDEL\r\n$" . $keyLen . "\r\n" . $key . "\r\n";
        }

        // action
        fwrite($this->_redisConnexion, $rawCommand);

        // reponse
        {
            $rawResponse = fgets($this->_redisConnexion);
            $res = ($rawResponse == ':1') ? true : false;
        }

        // retour
        unset($keyLen, $rawCommand, $rawResponse);
        return ($res);
    }

    /**
     * Recupere une chaine
     * @param string $key
     * @return string|null  Chaine trouvee.
     * @throws \Exception si n'arrive pas a se connecter.
     */
    public function getStr($key) {
        // verifs
        {
            if (empty($key))
                return null;
            if ($this->_isConnected == 0)
                $this->connect();
        }

        // preparation
        {
            $res = null;
            $keyLen = strlen($key);
            $rawCommand = "*2\r\n$3\r\nGET\r\n$" . $keyLen . "\r\n" . $key . "\r\n";
        }

        // ajout
        fwrite($this->_redisConnexion, $rawCommand);

        // prise de la longueur de la reponse
        {
            $rawResponse1 = fgets($this->_redisConnexion);
            if ($rawResponse1 == '$-1' . "\r\n") {
                // la cle n'existe pas / plus
                unset($res, $keyLen, $rawCommand, $rawResponse1);
                return (null);
            }
            $tmp = substr($rawResponse1, 1);
            $responseLen = intval($tmp);
            unset($rawResponse1, $tmp);
        }

        // prise de la reponse (par paquet de 1024 octets)
        {
            $tmp = '';
            $cpt = 0;
            while ($responseLen > 0) {
                $size = ($responseLen > 1024) ? 1024 : $responseLen;
                $tmp .= fread($this->_redisConnexion, $size);
                $responseLen -= $size;
            }
            $res = base64_decode($tmp);
            unset($tmp, $cpt, $size);
        }

        // retour
        unset($keyLen, $rawCommand, $responseLen, $tmp);
        return ($res);
    }

    /**
     * Enregistre une chaine
     * @param string $key
     * @param string $str
     * @return bool true si tout s'est bien passe. false sinon.
     * @throws \Exception si n'arrive pas a se connecter.
     */
    public function setStr($key, $str) {
        // verifs
        {
            if (empty($key) || empty($str))
                return (false);
            if ($this->_isConnected == 0)
                $this->connect();
        }

        // preparation
        {
            $res = false;
            $str2 = base64_encode($str);
            $keyLen = strlen($key);
            $strLen = strlen($str2);
            $rawCommand = "*3\r\n$3\r\nSET\r\n$" . $keyLen . "\r\n" . $key . "\r\n$" . $strLen . "\r\n" . $str2 . "\r\n";
        }

        // ajout
        fwrite($this->_redisConnexion, $rawCommand);

        // prise de la reponse
        {
            $rawResponse = fgets($this->_redisConnexion);
            if ($rawResponse == '+OK')
                $res = true;
        }

        // retour
        unset($keyLen, $strLen, $rawCommand, $rawResponse, $str2);
        return ($res);
    }

    /**
     * Se connecte a redis.
     * @return \Exception Si la connexion avec redis est impossible.
     */
    private function connect() {
        // verifs
        if ($this->_isConnected == 1)
            return;
        $this->_redisConnexion = fsockopen($this->_hostName, $this->_port, $errCode, $errStr);
        if (!empty($errCode)) {
            throw new \Exception("Cannot connect to Redis : $errCode - $errStr", $errCode);
        }

        $this->_isConnected = 1;
    }

}
