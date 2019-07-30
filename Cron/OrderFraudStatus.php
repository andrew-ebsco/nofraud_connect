<?php

namespace NoFraud\Connect\Cron;

class OrderFraudStatus
{
    const ORDER_REQUEST = 'status';
    const REQUEST_TYPE  = 'GET';

    private $orders;
    private $configHelper;
    private $apiUrl;
    private $orderProcessor;

    public function __construct(
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orders,
        \NoFraud\Connect\Api\RequestHandler $requestHandler,
        \NoFraud\Connect\Helper\Config $configHelper,
        \NoFraud\Connect\Api\ApiUrl $apiUrl,
        \NoFraud\Connect\Order\Processor $orderProcessor
    ) {
        $this->orders = $orders;
        $this->requestHandler = $requestHandler;
        $this->configHelper = $configHelper;
        $this->apiUrl = $apiUrl;
        $this->orderProcessor = $orderProcessor;
    }

    public function execute() 
    {
        $orders = $this->readOrders();
        $this->updateOrdersFromNoFraudApiResult($orders);
    }

    public function readOrders()
    {
        $orders = $this->orders->create()
            ->addFieldToSelect('status')
            ->addFieldToSelect('increment_id')
            ->addFieldToSelect('entity_id')
            ->setOrder('status', 'desc');

        $select = $orders->getSelect()
            ->where('status = \''.$this->configHelper->getOrderStatusReview().'\'');

        return $orders;
    }

    public function updateOrdersFromNoFraudApiResult($orders) 
    {
        $apiUrl = $this->apiUrl->buildOrderApiUrl(self::ORDER_REQUEST,$this->configHelper->getApiToken());
        foreach ($orders as $order) {
            $orderSpecificApiUrl = $apiUrl.'/'.$order['increment_id'];
            $response = $this->requestHandler->send(null,$orderSpecificApiUrl,self::REQUEST_TYPE);

            if (isset($resultMap['http']['response']['body'])){
                $newStatus = $this->orderProcessor->getCustomOrderStatus($resultMap['http']['response']);
                $this->orderProcessor->updateOrderStatusFromNoFraudResult($newStatus, $order);

	        $order->save();

                if ($this->configHelper->getAutoCancel()) {
                    $this->orderProcessor->handleAutoCancel($order);
                }
            }
        }
    }
}