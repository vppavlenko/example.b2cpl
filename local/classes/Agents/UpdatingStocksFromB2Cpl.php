<?php
namespace WS\Agents;

use WS\B2CplApi\Service;
use WS\Tools\BaseAgent;
use WS\Tools\Module;

class UpdatingStocksFromB2Cpl extends BaseAgent {
    /**
     * Run agent function
     *
     * @return array Params next call
     */
    public function algorithm() {
        /** @var Service $updatingStocksFromB2Cpl */
        $updatingStocksFromB2Cpl = Module::getInstance()->getService('updatingStocksFromB2Cpl');
        $updatingStocksFromB2Cpl->removalStock();

        return [];
    }
}