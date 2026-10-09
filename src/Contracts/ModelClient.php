<?php

declare(strict_types=1);

namespace AgentStudio\Contracts;

use AgentStudio\Contracts\Model\ModelException;
use AgentStudio\Contracts\Model\ModelRequest;
use AgentStudio\Contracts\Model\ModelResponse;

interface ModelClient
{
    /**
     * @throws ModelException
     */
    public function generate(ModelRequest $request): ModelResponse;
}
