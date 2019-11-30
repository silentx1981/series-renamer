<?php

namespace SeriesRenamer\Core;

class Translate
{
    private $langs;

    public function __construct()
    {
        $this->langs = json_decode(file_get_contents('../language/de.json'), true);
    }

    public function translate($string, array $params)
    {
        $p = [];
        foreach($params as $key => $value)
            $p["%$key%"] = $value;

        return strtr($this->langs[$string] ?? '?', $p);
    }
}