<?php

namespace App\Message;

class ComputeAuditMessage
{
    public function __construct(public readonly int $auditId)
    {
    }
}
