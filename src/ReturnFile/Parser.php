<?php
/**
 * This Software is part of aryelgois/bank-interchange and is provided "as is".
 *
 * @see LICENSE
 */

namespace aryelgois\BankInterchange\ReturnFile;

use aryelgois\Utils;
use Symfony\Component\Yaml\Yaml;

/**
 * Parses Return Files into an array of Registry instances
 *
 * @author Aryel Mota Góis
 * @license MIT
 * @link https://www.github.com/aryelgois/bank-interchange
 */
class Parser
{
    protected $config;

    /**
     * Creates a new Parser Object
     *
     * @param string $config YAML with Return File layouts
     *                       (length, structure, patterns, maps)
     */
    public function __construct(string $config)
    {
        $this->config = Yaml::parse($config);
    }

    /**
     * Parses a Return File into an array
     *
     * @param string $return_file The contents of a Return File
     *
     * @return array With (array nested) Registry instances
     *
     * @throws ParseException If can not identify $return_file layout
     */
    public function parse(string $return_file)
    {
        $return_file = explode("\n", trim($return_file));
        $registries = [];
        $offset = 0;

        foreach ($this->config as $cnab => $config) {
            try {
                $result = self::doParse(
                    $cnab,
                    array_slice($config['structure'], 0, 1),
                    $return_file
                );

                if (!empty($result['registries'])) {
                    $registries = $result['registries'];
                    $offset = $result['offset'];
                    break;
                }
            } catch (ParseException $e) {
                /*
                 * Ignore the Exception. We are trying to figure out which
                 * layout $return_file has
                 */
            }
        }

        if (empty($registries)) {
            throw ParseException::undefinedLayout(array_keys($this->config));
        }

        $result = self::doParse(
            $cnab,
            array_slice($config['structure'], 1),
            $return_file,
            $offset
        );

        $registries = array_merge($registries, $result['registries']);

        return $registries;
    }

    /**
     * Recursively follows the structure of a Return File to extract fields
     *
     * @param string $cnab      Return File layout from config
     * @param array  $structure Structure tree to be used
     * @param array  $lines     Lines of Return File to be parsed
     * @param int    $offset    Current item in $lines
     *
     * @return array With keys 'offset' and 'registries'
     *
     * @throws \DomainException For invalid structure amount
     * @throws ParseException   For invalid registry
     */
    protected function doParse(
        string $cnab,
        array $structure,
        array $lines,
        int $offset = null
    ) {
        $result = [];
        $offset = $offset ?? 0;

        foreach ($structure as $registry_group) {
            $type = reset($registry_group);
            $amount = key($registry_group);

            if (!in_array($amount, ['unique', 'multiple'])) {
                $message = "Invalid structure amount '$amount' in $cnab";
                throw new \DomainException($message);
            }

            if (!is_array($type)) {
                $registries = Utils\Utils::arrayWhitelist(
                    $this->config[$cnab]['registries'],
                    explode(' ', $type)
                );
            }

            do {
                $previous_offset = $offset;
                if (is_array($type)) {
                    try {
                        $rec = self::doParse($cnab, $type, $lines, $offset);
                        $result = array_merge($result, [$rec['registries']]);
                        $offset = $rec['offset'];
                    } catch (ParseException $e) {
                        if ($amount !== 'multiple') {
                            throw $e;
                        }
                    }
                } else {
                    $registry = self::pregRegistry(
                        str_pad(
                            rtrim($lines[$offset]),
                            $this->config[$cnab]['registry_length']
                        ),
                        $registries,
                        $cnab
                    );

                    if ($registry !== null) {
                        $result[] = $registry;
                        $offset++;
                    } elseif ($amount === 'unique') {
                        throw ParseException::pregMismatch(
                            $cnab,
                            $registries,
                            $offset + 1
                        );
                    }
                }
            } while ($amount === 'multiple' && $offset > $previous_offset);
        }

        return [
            'offset' => $offset,
            'registries' => $result,
        ];
    }

    /**
     * Extracts fields from a Return File registry to fill a Registry instance
     *
     * @param string $registry   Line with fields to be extracted
     * @param array  $registries Sequence of pattern and map
     * @param string $cnab       Metadata for new Registry
     *
     * @return Registry On success
     * @return null     On failure
     */
    protected function pregRegistry(
        string $registry,
        array $registries,
        string $cnab
    ) {
        $result = null;
        foreach ($registries as $type => $matcher) {
            if (preg_match($matcher['pattern'], $registry, $matches)) {
                $match = array_combine(
                    $matcher['map'],
                    array_map('trim', array_slice($matches, 1))
                );
                $result = new Registry($cnab, $type, $match);
                break;
            }
        }
        return $result;
    }
}
