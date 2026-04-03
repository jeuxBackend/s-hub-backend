<?php

namespace App\Enums;

enum FeeStatus: string
{
    case Paid = 'paid';
    case Unpaid = 'unpaid';
    case Partial = 'partial';
}
