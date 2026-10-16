<?php

namespace App\Enums;

enum TransactionWorkflow: string
{
    case Legacy = 'legacy';
    case Direct = 'direct';
}
