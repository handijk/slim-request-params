<?php

declare(strict_types = 1);

namespace SlimRequestParams {

    abstract class RequestParameters extends \stdClass
    {
        // Derived classes must implement:
        // static $validated_parameters;

        protected $rules;

        public function __construct(array $rules = [])
        {
            assert(property_exists(get_called_class(), 'validated_parameters'));
            $this->rules = $rules;
        }

        static public function get(): \stdClass
        {
            return (object)static::$validated_parameters;
        }

        protected function validate(array $requestparams)
        {
            $params = [];
            $validations = [];
            $allow_any = false;

            if (empty($requestparams)) {
                $requestparams = [];
            }

            // parse rules, extract key and regex
            foreach ($this->rules as $rule) {

                // remember we saw the 'allow any' pattern
                if ($rule == '{*}') {
                    $allow_any = true;
                    continue;
                }

                // parse the rule
                if (preg_match("/^{(?<name>\w+):(?<pattern>.*)}(?:,(?<default>.+))?$/", $rule, $matches) == 0) {
                    throw new \Exception("Invalid validation pattern: " . $rule);
                }
                $validations[$matches['name']] = $matches['pattern'];

                // set the defaults
                if (!array_key_exists($matches['name'], $requestparams)) {

                    if (!isset($matches['default'])) {

                        // parameter is missing and no default either
                        throw new \InvalidArgumentException("Missing parameter: " . $matches['name']);

                    } elseif (strcasecmp($matches['default'], 'null') == 0) {

                        $params[$matches['name']] = null;

                    } elseif ($matches['pattern'] == '\boolean' and in_array($matches['default'], ['true', 'TRUE', 1])) {

                        $params[$matches['name']] = true;

                    } elseif ($matches['pattern'] == '\boolean' and in_array($matches['default'], ['false', 'FALSE', 0])) {

                        $params[$matches['name']] = false;

                    } elseif (strcasecmp($matches['default'], '\optional') != 0) {

                        // type corrections to the defaults
                        switch ($matches['pattern']) {
                            case '\boolean':
                                $params[$matches['name']] = (bool)$matches['default'];
                                break;

                            case '\int':
                                $params[$matches['name']] = (int)$matches['default'];
                                break;

                            case '\float':
                                $params[$matches['name']] = (float)$matches['default'];
                                break;

                            case '\date':
                                $params[$matches['name']] = (new \DateTime($matches['default']))->format('Y-m-d H:i:s');
                                break;

                            default:
                                $params[$matches['name']] = $matches['default'];
                                break;
                        }
                    }
                }
            }

            // loop parameters and validate according to rules
            foreach ($requestparams as $k => $v) {

                // handle unvalidatable keys
                if (!isset($validations[$k])) {
                    if (!$allow_any) {
                        throw new \InvalidArgumentException("Invalid parameter: " . $k);
                    } else {
                        $validations[$k] = '\raw';
                    }
                }

                // convert value(s) to array, preserve true nulls
                if ($v === null) {
                    $params[$k] = [null];
                } elseif (is_scalar($v)) {
                    $params[$k] = [$v];
                } else {
                    $params[$k] = $v;
                }

                // check and normalize each key and value
                foreach ($params[$k] as $kk => $vv) {

                    // convert null values to real null's, no validation needed
                    if (in_array($vv, ['NULL', 'null', null])) {

                        $params[$k][$kk] = null;

                    } else {

                        switch ($validations[$k]) {

                            case '\boolean':
                                if (in_array($vv, ['true', 'TRUE', 1])) {
                                    $validated = true;
                                    $params[$k][$kk] = true;
                                } elseif (in_array($vv, ['false', 'FALSE', 0])) {
                                    $validated = true;
                                    $params[$k][$kk] = false;
                                } else {
                                    $validated = false;
                                }
                                break;

                            case '\raw':
                                $validated = true;
                                break;

                            case '\base64json':
                                // to allow json as a value it needs to be base64 encoded
                                $params[$k][$kk] = base64_decode($vv);
                                $validated = null !== json_decode($params[$k][$kk]);
                                break;

                            case '\email':
                                $validated = false !== filter_var($vv, FILTER_VALIDATE_EMAIL);
                                break;

                            case '\int':
                                $validated = false !== filter_var($vv, FILTER_VALIDATE_INT);
                                $params[$k][$kk] = (int)$vv;
                                break;

                            case '\float':
                                $validated = false !== filter_var($vv, FILTER_VALIDATE_FLOAT);
                                $params[$k][$kk] = (float)$vv;
                                break;

                            case '\url':
                                $validated = false !== filter_var($vv, FILTER_VALIDATE_URL);
                                break;

                            case '\country':
                                $params[$k][$kk] = strtoupper($vv);
                                $validated = 0 < (preg_match("/^(?:[A-Za-z]{2})?$/", $vv));
                                break;

                            case '\nationality':
                                $params[$k][$kk] = strtoupper($vv);
                                $validated = 0 < (preg_match("/^(?:[A-Za-z]{2})?$/", $vv));
                                break;

                            case '\currency':
                                $params[$k][$kk] = strtoupper($vv);
                                $validated = 0 < (preg_match("/^(?:[[:alnum:]]{3})?$/", $vv));
                                break;

                            case '\language':
                                $params[$k][$kk] = strtoupper($vv);
                                $validated = 0 < (preg_match("/^(?:[A-Za-z]{2})?$/", $vv));
                                break;

                            case '\date':
                                try {
                                    $params[$k][$kk] = (new \DateTime($vv))->format('Y-m-d H:i:s');
                                    $validated = true;
                                } catch (\Throwable $t) {
                                    $validated = false;
                                }
                                break;

                            case '\timezone':
                                try {
                                    $params[$k][$kk] = (new \DateTimeZone($vv))->getName();
                                    $validated = true;
                                } catch (\Throwable $t) {
                                    $validated = false;
                                }
                                break;

                            default:
                                $validated = 0 < (preg_match("/^{$validations[$k]}$/", $vv));
                        }
                        if (!$validated) {
                            throw new \InvalidArgumentException("Invalid parameter value for key: $k ($vv)");
                        }
                    }
                }

                // convert single element value back to scalar
                if (1 == count($params[$k])) {
                    $params[$k] = $params[$k][0];
                }
            }
            // must be supplied by sub-class
            static::$validated_parameters = $params;
        }

    }
}
