<?php

namespace SeriesRenamer;

use ReflectionClass;
use SeriesRenamer\Core\Config;
use SeriesRenamer\Core\Translate;

class Renaming
{
    const STATUS_UNDEFINED = 0;
    const STATUS_OK = 200;
    const STATUS_NOT_MODIFIED = 304;
    const STATUS_NOT_FOUND = 404;
    const STATUS_CONFLICT = 409;

    private $config;

    public function __construct()
    {
        $reflect = new ReflectionClass($this);
        $this->config = Config::getConfigArray($reflect->getShortName().'.json');
    }

    public function run()
    {
        $files = $this->getFiles();
        $infoFiles = $this->getFileInfos($files);
        $data = $this->processRenaming($infoFiles);

        echo json_encode($data);
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
        $trans = new Translate();

        $result = [];
        foreach ($files as $file) {
            $raw = $file['raw'] ?? null;
            $new = $file['new'] ?? null;
            $row = [
                'file' => $file,
                'raw' => $raw,
                'message' => null,
                'status' => self::STATUS_UNDEFINED,
            ];

            if ($raw === null || $new === null) {
                $row['message'] = $trans->translate('renaming.notwork', ['file' => $file]);
                $row['status'] = self::STATUS_CONFLICT;
                $result[] = $row;
                continue;
            }

            if ($raw === $new) {
                $row['message'] = $trans->translate('renaming.notneed', ['file' => $raw]);
                $row['status'] = self::STATUS_OK;
                $result[] = $row;
                continue;
            }

            $rawFile = $this->config['filesDirectory'].'/'.$raw;
            if (!file_exists($rawFile)) {
                $row['message'] = $trans->translate('renaming.filenotfound', ['file' => $raw]);
                $row['status'] = self::STATUS_NOT_FOUND;
                $result[] = $row;
                continue;
            }

            $newFile = $this->config['filesDirectory'].'/'.$new;
            if (rename($rawFile, $newFile)) {
                $row['message'] = $trans->translate('renaming.completed', ['file' => $file, 'newfile' => $newFile]);
                $row['status'] = self::STATUS_OK;
            } else {
                $row['message'] = $trans->translate('renaming.notwork', ['file' => $raw]);
                $row['status'] = self::STATUS_CONFLICT;
            }
            $result[] = $row;
        }

        return $result;
    }
}