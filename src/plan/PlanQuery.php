<?php
/**
 * API for Billing
 *
 * @link      https://github.com/hiqdev/billing-hiapi
 * @package   billing-hiapi
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2017-2018, HiQDev (http://hiqdev.com/)
 */

namespace hiqdev\billing\hiapi\plan;

use hiqdev\billing\hiapi\models\Plan;

class PlanQuery extends \hiqdev\yii\DataMapper\query\Query
{
    /**
     * @var string
     */
    protected $modelClass = Plan::class;

    protected function attributesMap()
    {
        return [
            'id' => 'zt.obj_id',
            'name' => 'zt.name',
            'type' => [
                'name' => 'tt.name',
            ],
            'seller' => [
                'id' => 'zc.obj_id',
                'login' => 'zc.login',
                'seller' => [
                    'id' => 'cr.obj_id',
                    'login' => 'cr.login',
                ],
            ],
            'available_for' => new AvailableForField(),
        ];
    }

    public function initFrom()
    {
        return $this->from('tariff  zt')
            ->leftJoin('zref        tt', 'tt.obj_id = zt.type_id')
            ->leftJoin('zclient     zc', 'zc.obj_id = zt.client_id')
            ->leftJoin('zclient     cr', 'cr.obj_id = zc.seller_id');
    }
}
