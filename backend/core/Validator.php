<?php
class Validator
{
    public static function validarOrigen($schema)
    {
        $referer = basename(parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH));
        if (!in_array($referer, $schema['origenes'])) {
            return false;
        }
        return true;
    }

    public static function filtrarDatos($schema, $postData)
    {
        $data = [];
        foreach ($schema['campos'] as $campo) {
            if (isset($postData[$campo])) {
                $data[$campo] = $postData[$campo];
            }
        }
        return $data;
    }
}
