<?php

namespace SeriesRenamer;

class App
{
    public function run()
    {
        $renaming = new Renaming();
        $renaming->run();
    }
}