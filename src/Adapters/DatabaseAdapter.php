<?php

namespace Lauthz\Adapters;

use Lauthz\Models\Rule;
use Lauthz\Contracts\DatabaseAdapter as DatabaseAdapterContract;
use Casbin\Persist\AdapterHelper;

/**
 * DatabaseAdapter.
 *
 * @author techlee@qq.com
 */
class DatabaseAdapter implements DatabaseAdapterContract
{
    use AdapterHelper;

    /**
     * Rules eloquent model.
     *
     * @var Rule
     */
    protected $eloquent;

    /**
     * the DatabaseAdapter constructor.
     *
     * @param Rule $eloquent
     */
    public function __construct(Rule $eloquent)
    {
        $this->eloquent = $eloquent;
    }

    /**
     * savePolicyLine function.
     *
     * @param string $ptype
     * @param array  $rule
     *
     * @return void
     */
    public function savePolicyLine($ptype, array $rule)
    {
        $col['ptype'] = $ptype;
        foreach ($rule as $key => $value) {
            $col['v' . strval($key)] = $value;
        }

        $this->eloquent->create($col);
    }

    /**
     * savePoliciesLine function.
     *
     * @param string $ptype
     * @param array  $rule
     *
     * @return void
     */
    public function savePoliciesLine(string $ptype, array $rules)
    {
        $data = [];
        foreach ($rules as $v) {
            $val = [
                'ptype' => $ptype,
                'updated_at' => date('Y-m-d H:i:s', time())
            ];
            foreach ($v as $s => $r) {
                $val['v' . $s] = $r;
            }
            $data[] = $val;
        }
        return $this->eloquent->insert($data);
    }

    /**
     * loads all policy rules from the storage.
     *
     * @param Model $model
     *
     * @return mixed
     */
    public function loadPolicy($model)
    {
        $rows = $this->eloquent->getAllFromCache();

        foreach ($rows as $row) {
            $line = implode(', ', array_slice(array_values($row), 1));
            $this->loadPolicyLine(trim($line), $model);
        }
    }

    /**
     * saves all policy rules to the storage.
     *
     * @param Model $model
     *
     * @return bool
     */
    public function savePolicy($model)
    {
        foreach ($model->model['p'] as $ptype => $ast) {
            foreach ($ast->policy as $rule) {
                $this->savePolicyLine($ptype, $rule);
            }
        }

        foreach ($model->model['g'] as $ptype => $ast) {
            foreach ($ast->policy as $rule) {
                $this->savePolicyLine($ptype, $rule);
            }
        }

        return true;
    }


    /**
     * Adds a policy rule to the storage.
     * This is part of the Auto-Save feature.
     *
     * @param string $sec
     * @param string $ptype
     * @param array  $rule
     *
     * @return mixed
     */
    public function addPolicy($sec, $ptype, $rule)
    {
        return $this->savePolicyLine($ptype, $rule);
    }

    /**
     * Adds a policies rule to the storage.
     * This is part of the Auto-Save feature.
     *
     * @param string $sec
     * @param string $ptype
     * @param array  $rule
     *
     * @return mixed
     */
    public function addPolicies($sec, $ptype, $rules)
    {
        return $this->savePoliciesLine($ptype, $rules);
    }

    /**
     * This is part of the Auto-Save feature.
     *
     * @param string $sec
     * @param string $ptype
     * @param array  $rule
     *
     * @return mixed
     */
    public function removePolicy($sec, $ptype, $rule)
    {
        $count = 0;

        $instance = $this->eloquent->where('ptype', $ptype);

        foreach ($rule as $key => $value) {
            $instance->where('v' . strval($key), $value);
        }

        foreach ($instance->get() as $model) {
            if ($model->delete()) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * This is part of the Auto-Save feature.
     *
     * @param string $sec
     * @param string $ptype
     * @param array  $rule
     *
     * @return mixed
     */
    public function removePolicies(string $sec, string $ptype, array $rules)
    {
        return $this->eloquent->where('ptype', $ptype)->Where(function ($query) use ($rules) {
            foreach ($rules as $value) {
                $query->orWhere(function ($query_info) use ($value) {
                    $where = [];
                    foreach ($value as $k => $v) {
                        $where['v' . $k] = $v;
                    }
                    $query_info->Where($where);
                });
            }
        })->delete();
    }

    /**
     * RemoveFilteredPolicy removes policy rules that match the filter from the storage.
     * This is part of the Auto-Save feature.
     *
     * @param string $sec
     * @param string $ptype
     * @param int    $fieldIndex
     * @param mixed  ...$fieldValues
     *
     * @return mixed
     */
    public function removeFilteredPolicy($sec, $ptype, $fieldIndex, ...$fieldValues)
    {
        $count = 0;

        $instance = $this->eloquent->where('ptype', $ptype);
        foreach (range(0, 5) as $value) {
            if ($fieldIndex <= $value && $value < $fieldIndex + count($fieldValues)) {
                if ('' != $fieldValues[$value - $fieldIndex]) {
                    $instance->where('v' . strval($value), $fieldValues[$value - $fieldIndex]);
                }
            }
        }

        foreach ($instance->get() as $model) {
            if ($model->delete()) {
                ++$count;
            }
        }

        return $count;
    }
}
