<?php namespace Chaos\Common;

use Zend\Db\Sql\Predicate\Predicate;

/**
 * Class BaseServiceTrait
 * @author ntd1712
 *
 * @method \Noodlehaus\ConfigInterface|mixed getConfig(string $key = null, mixed $default = null)
 * @method IBaseRepository|\Doctrine\ORM\EntityRepository getRepository(string $name = null)
 * @method IBaseService getService(string $name = null)
 * @method IBaseEntity getUser(string $token = null)
 */
trait BaseServiceTrait
{
    /**
     * Prepare filter parameters
     *
     * @param   array|string $binds A bind variable array
     * @param   \Zend\Db\Sql\Predicate\PredicateInterface $predicate
     * @return  Predicate
     */
    public function prepareFilterParams($binds = [], $predicate = null)
    {
        if (null === $predicate)
        {
            $predicate = new Predicate;
        }

        $fields = $this->getRepository()->fields;

        if (is_array($binds))
        {
            foreach ($binds as $v)
            {
                if (!is_array($v) || empty($v['predicate']))
                {
                    continue;
                }

                if (isset($v['nesting']) &&
                   (Enums\PredicateType::NEST === $v['nesting'] || Enums\PredicateType::UNNEST === $v['nesting']))
                {
                    $predicate = $predicate->{$v['nesting']}();
                }

                if (isset($v['combine']) &&
                   (Predicate::OP_OR === $v['combine'] || strtolower(Predicate::OP_OR) === $v['combine']))
                {
                    $predicate->or;
                }

                switch ($v['predicate'])
                {
                    case Enums\PredicateType::BETWEEN:
                    case Enums\PredicateType::NOT_BETWEEN:
                        if (empty($v['identifier']) || !isset($v['minValue']) || !isset($v['maxValue']) ||
                           !isset($fields[$v['identifier']]))
                        {
                            continue;
                        }

                        /* @see \Zend\Db\Sql\Predicate\Predicate::between
                         * @see \Zend\Db\Sql\Predicate\Predicate::notBetween */
                        $predicate->{$v['predicate']}($v['identifier'],
                            "'" . $this->filter($v['minValue'], true) . "'",
                            "'" . $this->filter($v['maxValue'], 86399) . "'");
                        break;
                    case Enums\PredicateType::EQUAL_TO:
                    case Enums\PredicateType::NOT_EQUAL_TO:
                    case Enums\PredicateType::GREATER_THAN:
                    case Enums\PredicateType::LESS_THAN:
                    case Enums\PredicateType::GREATER_THAN_OR_EQUAL_TO:
                    case Enums\PredicateType::LESS_THAN_OR_EQUAL_TO:
                    case Enums\PredicateType::EQ:
                    case Enums\PredicateType::NEQ:
                    case Enums\PredicateType::GT:
                    case Enums\PredicateType::LT:
                    case Enums\PredicateType::GTE:
                    case Enums\PredicateType::LTE:
                        if (!isset($v['left']) || !isset($v['right']))
                        {
                            continue;
                        }

                        if (empty($v['leftType']) || Predicate::TYPE_VALUE !== $v['leftType'])
                        {
                            $v['leftType'] = Predicate::TYPE_IDENTIFIER;
                        }

                        if (empty($v['rightType']) || Predicate::TYPE_IDENTIFIER !== $v['rightType'])
                        {
                            $v['rightType'] = Predicate::TYPE_VALUE;
                        }

                        if ($v['leftType'] == $v['rightType'])
                        {
                            $v['leftType'] = Predicate::TYPE_IDENTIFIER;
                            $v['rightType'] = Predicate::TYPE_VALUE;
                        }

                        if (Predicate::TYPE_IDENTIFIER !== $v['leftType'])
                        {
                            $v['left'] = "'" . $this->filter($v['left'], true) . "'";
                        }
                        elseif (!isset($fields[$v['left']]))
                        {
                            continue;
                        }

                        if (Predicate::TYPE_IDENTIFIER !== $v['rightType'])
                        {
                            $v['right'] = "'" . $this->filter($v['right'], true) . "'";
                        }
                        elseif (!isset($fields[$v['right']]))
                        {
                            continue;
                        }

                        /* @see \Zend\Db\Sql\Predicate\Predicate::equalTo
                         * @see \Zend\Db\Sql\Predicate\Predicate::notEqualTo
                         * @see \Zend\Db\Sql\Predicate\Predicate::lessThan
                         * @see \Zend\Db\Sql\Predicate\Predicate::greaterThan
                         * @see \Zend\Db\Sql\Predicate\Predicate::lessThanOrEqualTo
                         * @see \Zend\Db\Sql\Predicate\Predicate::greaterThanOrEqualTo */
                        $predicate->{$v['predicate']}($v['left'], $v['right'], $v['leftType'], $v['rightType']);
                        break;
                    case Enums\PredicateType::EXPR:
                    case Enums\PredicateType::EXPRESSION:
                        if (empty($v['expression']) || !isset($v['parameters']))
                        {
                            continue;
                        }

                        if (!is_array($v['parameters']))
                        {
                            $v['parameters'] = [$v['parameters']];
                        }

                        foreach ($v['parameters'] as $key => &$value)
                        {
                            if (!is_scalar($value))
                            {
                                unset($v['parameters'][$key]);
                            }
                            elseif (!isset($fields[$value]))
                            {
                                $value = "'" . str_replace('%', '%%', $this->filter($value)) . "'";
                            }
                        }

                        unset($value);
                        $v['expression'] = str_replace(['&lt;', '&gt;'], ['<', '>'],
                            $this->filter($v['expression']));

                        $predicate->expression($v['expression'], array_values($v['parameters']));
                        break;
                    case Enums\PredicateType::IN:
                    case Enums\PredicateType::NIN:
                    case Enums\PredicateType::NOT_IN:
                        if (empty($v['identifier']) || empty($v['valueSet']) || !is_array($v['valueSet']))
                        {
                            continue;
                        }

                        if (is_array($v['identifier']))
                        {
                            foreach ($v['identifier'] as $key => $value)
                            {
                                if (!isset($fields[$value]))
                                {
                                    unset($v['identifier'][$key]);
                                }
                            }

                            if (empty($v['identifier']))
                            {
                                continue;
                            }

                            $v['identifier'] = array_values($v['identifier']);
                        }
                        elseif (!isset($fields[$v['identifier']]))
                        {
                            continue;
                        }

                        foreach ($v['valueSet'] as &$value)
                        {
                            $value = "'" . $this->filter($value) . "'";
                        }

                        unset($value);

                        /* @see \Zend\Db\Sql\Predicate\Predicate::in
                         * @see \Zend\Db\Sql\Predicate\Predicate::notIn */
                        $predicate->{$v['predicate']}($v['identifier'], $v['valueSet']);
                        break;
                    case Enums\PredicateType::IS_NOT_NULL:
                    case Enums\PredicateType::IS_NULL:
                        if (empty($v['identifier']) || !isset($fields[$v['identifier']]))
                        {
                            continue;
                        }

                        /* @see \Zend\Db\Sql\Predicate\Predicate::isNull
                         * @see \Zend\Db\Sql\Predicate\Predicate::isNotNull */
                        $predicate->{$v['predicate']}($v['identifier']);
                        break;
                    case Enums\PredicateType::LIKE:
                    case Enums\PredicateType::NOT_LIKE:
                        if (empty($v['identifier']) || empty($v[$v['predicate']]) ||
                           !isset($fields[$v['identifier']]) || !is_string($v[$v['predicate']]))
                        {
                            continue;
                        }

                        $v[$v['predicate']] = "'%" . str_replace('%', '%%', $this->filter($v[$v['predicate']])) . "%'";

                        /* @see \Zend\Db\Sql\Predicate\Predicate::like
                         * @see \Zend\Db\Sql\Predicate\Predicate::notLike */
                        $predicate->{$v['predicate']}($v['identifier'], $v[$v['predicate']]);
                        break;
                    case Predicate::TYPE_LITERAL:
                        if (empty($v['literal']) || !is_string($v['literal']))
                        {
                            continue;
                        }

                        $predicate->literal(str_replace(['&lt;', '&gt;', '&#39;', '&#039;'], ['<', '>', "'", "'"],
                            $this->filter($v['literal'])));
                        break;
                    default:
                }
            }
        }
        elseif (is_string($binds))
        {
            if (property_exists($predicate, 'excludes'))
            {
                foreach ($predicate->excludes as $v)
                {
                    unset($fields[$v]);
                }
            }

            $predicateSet = new Predicate;
            $searchable = $this->getConfig('minSearchChars') <= strlen($binds);
            $binds = $this->filter($binds);
            $count = 0;

            foreach ($fields as $k => $v)
            {
                if ((Types\Type::STRING_TYPE === $v['type'] || Types\Type::TEXT_TYPE === $v['type']) &&
                   (($isChar = isset($v['options']) && isset($v['options']['fixed'])) || $searchable))
                {
                    $predicateSet->or;
                    isset($isChar) && $isChar ?
                        $predicateSet->equalTo($k, "'" . $binds . "'") :
                        $predicateSet->like($k, "'%" . str_replace('%', '%%', $binds) . "%'");

                    if (CHAOS_SQL_LIKE_LIMIT <= ++$count)
                    {
                        break;
                    }
                }
            }

            $predicate->predicate($predicateSet);
        }

        return $predicate;
    }

    /**
     * Prepare order parameters
     *
     * @param   array $binds A bind variable array
     * @return  array
     */
    public function prepareOrderParams(array $binds = [])
    {
        $orderSet = [];
        $fields = $this->getRepository()->fields;

        foreach ($binds as $v)
        {
            if (!is_array($v) || empty($v['property']) || !isset($fields[$v['property']]))
            {
                continue;
            }

            $orderSet[$v['property']] = empty($v['direction']) || !is_string($v['direction']) ||
                Enums\PredicateType::DESC !== strtoupper($v['direction']) ?
                Enums\PredicateType::ASC : Enums\PredicateType::DESC;

            if (!empty($v['nulls']) && Enums\PredicateType::has($nulls = 'NULLS ' . strtoupper($v['nulls'])))
            {
                $orderSet[$v['property']] .= ' ' . (Enums\PredicateType::NULLS_FIRST === $nulls ?
                    Enums\PredicateType::NULLS_FIRST : Enums\PredicateType::NULLS_LAST);
            }
        }

        return $orderSet;
    }

    /**
     * Prepare pager parameters
     *
     * @param   array $binds A bind variable array
     * @return  array
     */
    public function preparePagerParams(array $binds = [])
    {
        if (!(isset($binds['CurrentPageNumber']) || isset($binds['CurrentPageStart'])))
        {
            return false;
        }

        if (isset($binds['ItemCountPerPage']))
        {
            $maxItemsPerPage = $this->getConfig('maxItemsPerPage');
            $binds['ItemCountPerPage'] = (int)$binds['ItemCountPerPage'];

            if (1 > $binds['ItemCountPerPage'])
            {
                $binds['ItemCountPerPage'] = 1;
            }
            elseif ($maxItemsPerPage < $binds['ItemCountPerPage'])
            {
                $binds['ItemCountPerPage'] = $maxItemsPerPage;
            }
        }
        else
        {
            $binds['ItemCountPerPage'] = $this->getConfig('itemsPerPage');
        }

        if (isset($binds['CurrentPageNumber']))
        {
            $binds['CurrentPageNumber'] = (int)$binds['CurrentPageNumber'];

            if (1 > $binds['CurrentPageNumber'])
            {
                $binds['CurrentPageNumber'] = 1;
            }

            if (!isset($binds['CurrentPageStart']))
            {
                $binds['CurrentPageStart'] = $binds['ItemCountPerPage'] * ($binds['CurrentPageNumber'] - 1);
            }
        }

        if (isset($binds['CurrentPageStart']))
        {
            $binds['CurrentPageStart'] = (int)$binds['CurrentPageStart'];

            if (0 > $binds['CurrentPageStart'])
            {
                $binds['CurrentPageStart'] = 0;
            }

            if (!isset($binds['CurrentPageNumber']))
            {
                $binds['CurrentPageNumber'] = $binds['CurrentPageStart'] / $binds['ItemCountPerPage'] + 1;
            }
        }

        return $binds;
    }

    /**
     * Return the string $value, converting characters to
     * their corresponding HTML entity equivalents where they exist
     *
     * @param   string $value
     * @param   bool $checkDate
     * @return  string
     * @throws  Exceptions\LengthException
     */
    public function filter($value, $checkDate = false)
    {
        if (is_blank($value) || !is_scalar($value))
        {
            return '';
        }

        $value = trim($value);

        if (false !== $checkDate && 0 !== preg_match(CHAOS_MATCH_DATE, $value, $matches))
        {
            $filtered = date($this->getConfig('dateFormat'),
                is_bool($checkDate) ? strtotime($matches[0]) : strtotime($matches[0]) + $checkDate);
        }
        else
        {
            $filtered = htmlentities($value, ENT_QUOTES);

            if (strlen($value) && !strlen($filtered) && function_exists('iconv'))
            {
                $value = iconv('', $this->getConfig('charset') . '//IGNORE', $value);
                $filtered = htmlentities($value, ENT_QUOTES);

                if (!strlen($filtered))
                {
                    throw new Exceptions\LengthException('Encoding mismatch has resulted in htmlentities errors');
                }
            }
        }

        return $filtered;
    }

    /**
     * Fire a specified event
     *
     * @param   string $name The event name
     * @param   array $parameter Zero or more parameters to be passed to the event
     * @param   object $instance
     * @return  bool TRUE on success; FALSE otherwise
     */
    public function fireEvent($name, &$parameter, $instance = null)
    {
        if (method_exists($instance ?: $this, $name))
        {
            if (null !== ($result = call_user_func([$instance ?: $this, $name], $parameter)))
            {
                $parameter = $result;
            }

            return true;
        }

        return false;
    }

    /**
     * Get the model class name
     *
     * @param   int $options The bitmask index; defaults to DEBUG_BACKTRACE_IGNORE_ARGS
     * @param   int $limit Limit the number of stack frames returned
     * @return  array
     */
    private function getModel($options = DEBUG_BACKTRACE_IGNORE_ARGS, $limit = 3)
    {
        list(,, $caller) = debug_backtrace($options, $limit);
        $className = preg_replace('/(.*)\\\\services\\\\.*service$/i', '$1\Models\\' . ucfirst($caller['function']),
            get_called_class());

        return ['name' => $className, 'exist' => class_exists($className)];
    }
}