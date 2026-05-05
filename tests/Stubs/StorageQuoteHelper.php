<?php

namespace Lvntr\StarterKit\Tests\Stubs;

use Lvntr\StarterKit\Domain\FileManager\Concerns\ResolvesMediaModel;

/**
 * ResolvesMediaModel trait'ini dışarıya açan minimal helper sınıfı.
 *
 * computeStorageUsed() ve storageQuotaBytes() korumalı (protected)
 * metodlar olduğundan doğrudan test edilemiyor; bu wrapper public
 * proxy olarak çalışır.
 */
class StorageQuoteHelper
{
    use ResolvesMediaModel;

    public function getStorageUsed(): int
    {
        return $this->computeStorageUsed();
    }

    public function getQuotaBytes(): int
    {
        return $this->storageQuotaBytes();
    }
}
