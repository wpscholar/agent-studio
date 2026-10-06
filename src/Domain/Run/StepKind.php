<?php

declare(strict_types=1);

namespace WPAgents\Domain\Run;

enum StepKind: string
{
    /** The model produced a reply. */
    case Model = 'model';

    /** The model asked for a tool. */
    case ToolCall = 'tool_call';

    /** A tool returned a result. */
    case ToolResult = 'tool_result';

    /** An approval was requested or decided. */
    case Approval = 'approval';

    /** Other information worth showing in the run log. */
    case Note = 'note';

    /** Something went wrong. */
    case Error = 'error';
}
