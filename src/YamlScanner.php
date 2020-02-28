<?php

namespace L5Swagger;

class YamlScanner
{
    public function getYaml($directory, $options)
    {
        $exclude = array_key_exists('exclude', $options) ? $options['exclude'] : null;

        $finder = \OpenApi\Util::finder($directory, $exclude);
        foreach ($finder as $file) {
            $tokens[] = $this->getTokens($file->getPathname());
        }

        $docComments = $this->getDocCommentsFromTokens($tokens);
        $rawYaml = $this->stripYamlFromDocComments($docComments);
        $levelOneKeysDeDuped = $this->removeLevelOneDuplicateKeysFromYaml($rawYaml);
        foreach ($levelOneKeysDeDuped as $key => $levelOneKeysDeDupedArray) {
            $levelOneKeysDeDuped[$key] = $this->removeLevelTwoDuplicateKeysFromYaml($levelOneKeysDeDupedArray);
        }

        foreach ($levelOneKeysDeDuped as $keyLine => $yamlLinesArray) {
            $yamlLines[] = $keyLine;

            foreach ($yamlLinesArray as $subLine) {
                $yamlLines[] = $subLine;
            }
        }

        $yaml = implode(PHP_EOL, $yamlLines);

        return $yaml;
    }

    private function getDocCommentsFromTokens(array $tokens) : array
    {
        $oaDocComments = [];

        array_walk_recursive($tokens, function ($item) use (&$oaDocComments) {
            if (strpos($item, '@OA_YAML')) {
                $oaDocComments[] = $item;
            }
        });

        return $oaDocComments;
    }

    private function stripYamlFromDocComments(array $oaDocComments) : array
    {
        foreach ($oaDocComments as $oaDocComment) {
            $lines = explode(PHP_EOL, $oaDocComment);

            $yamlLines = array_filter($lines, function ($line) {
                return $line && !preg_match('/^\s*\*|\s*\/\*\*/', $line);
            });

            if (preg_match('/^(\s+)/', array_values($yamlLines)[0], $matches)) {
                $yamlLines = array_map(function ($line) use ($matches) {
                    return preg_replace("/^{$matches[1]}/", '', $line);
                }, $yamlLines);
            }

            $newRawYamlLinesArray[] = $yamlLines;
        }

        return $newRawYamlLinesArray;
    }

    private function removeLevelOneDuplicateKeysFromYaml(array $newRawYamlLinesArray) : array
    {
        $yamlArray = [];

        foreach ($newRawYamlLinesArray as $newRawYamlLines) {
            foreach ($newRawYamlLines as $newYamlLine) {
                if (!preg_match('/^\s/', $newYamlLine)) {
                    if (!trim($newYamlLine)) {
                        continue;
                    }

                    $key = trim($newYamlLine);

                    $newYamlLines[$key][] = PHP_EOL;
                    continue;
                }

                $newYamlLines[$key][] = $newYamlLine;
            }

            $yamlArray = array_merge($yamlArray, $newYamlLines);
        }

        return $yamlArray;
    }

    private function removeLevelTwoDuplicateKeysFromYaml(array $newRawYamlLines)
    {
        $yamlArray = [];

        foreach ($newRawYamlLines as $newYamlLine) {
            if (!preg_match('/^\s\s\s/', $newYamlLine)) {
                if (!trim($newYamlLine)) {
                    continue;
                }

                $key = '  ' . trim($newYamlLine);

                $newYamlLines[$key][] = PHP_EOL;
                continue;
            }

            $newYamlLines[$key][] = $newYamlLine;
        }

        $yamlArray = array_merge($yamlArray, $newYamlLines ?? []);

        foreach ($yamlArray as $keyLine => $yamlLinesArray) {
            $yamlLines[] = $keyLine;

            foreach ($yamlLinesArray as $subLine) {
                $yamlLines[] = $subLine;
            }
        }

        return $yamlLines ?? [];
    }

    /**
     * Extract and process all doc-comments from a file.
     *
     * @param string $filename Path to a php file.
     *
     * @return Analysis
     */
    private function getTokens($filename)
    {
        if (function_exists('opcache_get_status') && function_exists('opcache_get_configuration')) {
            if (empty($GLOBALS['openapi_opcache_warning'])) {
                $GLOBALS['openapi_opcache_warning'] = true;
                $status = opcache_get_status();
                $config = opcache_get_configuration();
                if ($status['opcache_enabled'] && $config['directives']['opcache.save_comments'] == false) {
                    Logger::warning("php.ini \"opcache.save_comments = 0\" interferes with extracting annotations.\n[LINK] http://php.net/manual/en/opcache.configuration.php#ini.opcache.save-comments");
                }
            }
        }

        return token_get_all(file_get_contents($filename));
    }
}