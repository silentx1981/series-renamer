<?php

namespace SeriesRenamer\Core;

class Config
{
    public static function getConfigArray($filename)
    {
        $file = "../config/$filename";
        $result = json_decode(file_get_contents($file), true);

        return $result;
    }
}
