<?php

namespace App\Enums;

enum CateringUnitBillSyncStatus: string
{
    case Synced = 'synced';
    case Stale = 'stale';
    case Unsynced = 'unsynced';
    case NoData = 'no_data';
}
