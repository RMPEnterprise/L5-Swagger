<?php

namespace L5Swagger;

use Symfony\Component\Yaml\Parser;

class FakeSwagger
{
    protected $yaml;

    public $server;

    public function __construct($yaml)
    {
        $this->yaml = $yaml;
    }

    public function saveAs($file)
    {
        $yamlParser = new Parser();
        $array = $yamlParser->parse($this->yaml);

        file_put_contents($file, json_encode($array, JSON_PRETTY_PRINT));
    }
}