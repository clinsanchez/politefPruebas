<?php
// Ripcord PHP XML-RPC Client - Archivo completo en uno solo
// Este archivo es una versión unificada de Ripcord para proyectos simples

if (!function_exists('ripcord')) {
    function ripcord($url, $options = array()) {
        return new Ripcord_Client($url, $options);
    }
}

class Ripcord_Client {
    private $url;
    private $options;

    public function __construct($url, $options = array()) {
        $this->url = $url;
        $this->options = $options;
    }

    public function __call($method, $params) {
        $request = xmlrpc_encode_request($method, $params);

        $context = stream_context_create(array('http' => array(
            'method'  => "POST",
            'header'  => "Content-Type: text/xml\r\nUser-Agent: Ripcord Client\r\n",
            'content' => $request
        )));

        $file = file_get_contents($this->url, false, $context);
        if ($file === false) {
            throw new Exception('Error de comunicación con Odoo.');
        }

        $response = xmlrpc_decode($file);

        if (is_array($response) && xmlrpc_is_fault($response)) {
            throw new Exception('Error XML-RPC: ' . $response['faultString']);
        }

        return $response;
    }
}

class ripcord {
    public static function client($url) {
        return new Ripcord_Client($url);
    }
}
