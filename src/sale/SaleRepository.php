<?php
/**
 * API for Billing
 *
 * @link      https://github.com/hiqdev/billing-hiapi
 * @package   billing-hiapi
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2017-2018, HiQDev (http://hiqdev.com/)
 */

namespace hiqdev\billing\hiapi\sale;

use hiqdev\php\billing\action\ActionInterface;
use hiqdev\php\billing\customer\CustomerInterface;
use hiqdev\php\billing\order\OrderInterface;
use hiqdev\php\billing\plan\PlanInterface;
use hiqdev\php\billing\sale\Sale;
use hiqdev\php\billing\sale\SaleInterface;
use hiqdev\php\billing\sale\SaleRepositoryInterface;
use hiqdev\yii\DataMapper\expressions\CallExpression;
use hiqdev\yii\DataMapper\expressions\HstoreExpression;
use hiqdev\yii\DataMapper\models\relations\Bucket;
use hiqdev\yii\DataMapper\query\Specification;
use hiqdev\yii\DataMapper\repositories\BaseRepository;
use Yii;
use yii\db\Query;

class SaleRepository extends BaseRepository implements SaleRepositoryInterface
{
    /** {@inheritdoc} */
    public $queryClass = SaleQuery::class;

    public function findId(SaleInterface $sale)
    {
        if ($sale->hasId()) {
            return $sale->getId();
        }
        $hstore = new HstoreExpression(array_filter([
            'buyer'     => $sale->getCustomer()->getLogin(),
            'buyer_id'  => $sale->getCustomer()->getId(),
            'object_id' => $sale->getTarget()->getId(),
            'tariff_id' => $sale->getPlan()->getId(),
        ]));
        $call = new CallExpression('sale_id', [$hstore]);
        $command = (new Query())->select($call);

        return $command->scalar($this->db);
    }

    /**
     * @param OrderInterface $order
     * @return Sale[]|SaleInterface[]
     */
    public function findByOrder(OrderInterface $order)
    {
        return array_map([$this, 'findByAction'], $order->getActions());
    }

    /**
     * @param ActionInterface $action
     * @return SaleInterface
     */
    public function findByAction(ActionInterface $action)
    {
        $client_id = $action->getCustomer()->getId();
        $type = $action->getTarget()->getType();

        if ($type === 'certificate') {
            //// XXX tmp crutch
            $class_id = new CallExpression('class_id', ['certificate']);
            $cond = empty($client_id)
                ? $this->buildSellerCond($action->getCustomer()->getSeller())
                : [
                    'target-id' => $class_id,
                    'customer-id' => $client_id,
                ];
        } elseif ($type === 'server') {
            $cond = [
                'target-id' => $action->getTarget()->getId(),
                'customer-id' => $client_id,
            ];
        } else {
            throw new \Exception('not implemented for: ' . $type);
        }

        $spec = Yii::createObject(Specification::class)
            /// XXX how to pass if we want with prices into joinPlans?
            ->with('plans')
            ->where($cond);

        return $this->findOne($spec);
    }

    protected function buildSellerCond(CustomerInterface $seller)
    {
        return [
            'customer-id'   => $seller->getId(),
            'seller-id'     => $seller->getId(),
        ];
    }

    protected function joinPlans(&$rows)
    {
        $bucket = Bucket::fromRows($rows, 'plan-id');
        $spec = (new Specification())
            ->with('prices')
            ->where(['id' => $bucket->getKeys()]);
        $raw_plans = $this->getRepository(PlanInterface::class)->queryAll($spec);
        /// TODO for SilverFire: try to do with bucket
        $plans = [];
        foreach ($raw_plans as $plan) {
            $plans[$plan['id']] = $plan;
        }
        foreach ($rows as &$sale) {
            $sale['plan'] = $plans[$sale['plan-id']];
        }
    }

    /**
     * @param SaleInterface $sale
     */
    public function save(SaleInterface $sale)
    {
        $hstore = new HstoreExpression([
            'object_id'     => $sale->getTarget()->getId(),
            'contact_id'    => $sale->getCustomer()->getId(),
            'tariff_id'     => $sale->getPlan() ? $sale->getPlan()->getId() : null,
            'time'          => $sale->getTime()->format('c'),
        ]);
        $call = new CallExpression('sale_object', [$hstore]);
        $command = (new Query())->select($call);
        $sale->setId($command->scalar($this->db));
    }
}
