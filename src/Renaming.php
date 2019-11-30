<?php

namespace SeriesRenamer;

use SeriesRenamer\Core\Config;
use ReflectionClass;

class Renaming
{
    private $config;

    public function __construct()
    {
        $reflect = new ReflectionClass($this);
        $this->config = Config::getConfigArray($reflect->getShortName().'.json');
    }

    public function run()
    {
        echo "<pre>";
        echo "Run Renaming <hr>";

        $files = $this->getFiles();
        $infoFiles = $this->getFileInfos($files);
        $this->processRenaming($infoFiles);

    }

    private function getFiles()
    {
        $data = scandir($this->config['filesDirectory']);
        $result = [];
        foreach ($data as $value)
            if (!in_array($value, $this->config['ignoredFileNames']))
                $result[] = $value;

        return $result;
    }

    private function getFileInfos(array $files)
    {
        $result = [];
        foreach ($files as $file) {
            $raw = $file;
            $season = $this->getSeason($file);
            $episode = $this->getEpisode($file);
            $serie = $this->getSerie($file);
            $ending = mb_substr($file, strrpos($file, '.') + 1);
            $seasonString = "S".sprintf('%02d', $season);
            $episodeString = "E".sprintf('%02d', $episode);
            $new = "$serie"."_"."$seasonString$episodeString.$ending";

            $result[] = [
                'raw' => $raw,
                'season' => $season,
                'episode' => $episode,
                'serie' => $serie,
                'ending' => $ending,
                'new' => $new,
            ];
        }

        return $result;
    }

    private function getSeason($name) {
        $matches = [];
        preg_match('/(S|s){1}([0-9]{1,2})/', $name, $matches);
        $match = $matches[0] ?? '';
        $match = mb_strtoupper($match);
        $result = (int) str_replace('S', '', $match);

        return $result;
    }

    private function getEpisode($name) {
        $matches = [];
        preg_match('/(E|e){1}([0-9]{1,2})/', $name, $matches);
        $match = $matches[0] ?? '';
        $match = mb_strtoupper($match);
        $result = (int) str_replace('E', '', $match);

        return $result;
    }

    private function getSerie($name)
    {
        $mapping = Config::getConfigArray('series_name_mapping.json');
        $matches = [];
        preg_match('/(.){1}(S|s){1}([0-9]{1,2})(E|e){1}([0-9]{1,2})/', $name, $matches);
        $match = $matches[0] ?? '';
        $pos = mb_strpos($name, $match);
        if ($pos === -1)
            return null;

        $result = mb_substr($name, 0, $pos);
        foreach ($mapping as $map)
            $result = preg_replace("/$map[pattern]/", $map['return'], $result);

        return $result;
    }

    private function processRenaming($files)
    {
        foreach ($files as $file) {
            $raw = $file['raw'] ?? null;
            $new = $file['new'] ?? null;

            if ($raw === null || $new === null) {
                echo "Folgende Datei konnte nicht umbenannt werden <br>";
                print_r($file);
                echo '<hr>';
                continue;
            }

            if ($raw === $new) {
                echo "$raw muss nicht geändert werden <br>";
                continue;
            }

            $rawFile = $this->config['filesDirectory'].'/'.$raw;
            if (!file_exists($rawFile)) {
                echo "$raw konnte nicht gefunden werden <br>";
                continue;
            }

            $newFile = $this->config['filesDirectory'].'/'.$new;
            if (rename($rawFile, $newFile)) {
                echo "$raw wurde in $new umbenannt <br>";
            } else {
                echo "$raw konnte nicht in $new umbenannt werden <br>";
            }

        }
    }
}