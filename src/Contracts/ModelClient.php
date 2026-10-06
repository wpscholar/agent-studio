<?php

declare(strict_types=1);

namespace WPAgents\Contracts;

use WPAgents\Contracts\Model\ModelException;
use WPAgents\Contracts\Model\ModelRequest;
use WPAgents\Contracts\Model\ModelResponse;

interface ModelClient
{
    /**
     * @throws ModelException
     */
    public function generate(ModelRequest $request): ModelResponse;
}
