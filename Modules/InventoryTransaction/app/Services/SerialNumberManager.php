<?php

namespace Modules\InventoryTransaction\Services;

use Illuminate\Support\Collection;
use Modules\InventoryTransaction\Enums\SerialNumberStatus;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Models\SerialNumber;

class SerialNumberManager
{
    /**
     * Create serial numbers on RECEIVE posting.
     */
    public function createSerials(
        string $orgId,
        int $goodsId,
        ?int $lotId,
        int $warehouseId,
        int $locationId,
        array $serialNumbers
    ): Collection {
        $created = collect();

        foreach ($serialNumbers as $serialNo) {
            // Check existing first for clean exception message
            $existing = SerialNumber::where('organization_id', $orgId)
                ->where('serial_no', $serialNo)
                ->first();

            if ($existing) {
                throw new StockDocumentException(
                    "Serial number '{$serialNo}' already exists in the organization",
                    'SERIAL_NUMBER_ALREADY_EXISTS',
                    409
                );
            }

            try {
                $serial = SerialNumber::create([
                    'organization_id' => $orgId,
                    'goods_id' => $goodsId,
                    'lot_id' => $lotId,
                    'serial_no' => $serialNo,
                    'warehouse_id' => $warehouseId,
                    'location_id' => $locationId,
                    'status' => SerialNumberStatus::IN_STOCK,
                ]);
                $created->push($serial);
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                throw new StockDocumentException(
                    "Serial number '{$serialNo}' already exists",
                    'SERIAL_NUMBER_ALREADY_EXISTS',
                    409
                );
            }
        }

        return $created;
    }

    /**
     * Lock serial numbers ordered strictly by id ASC.
     */
    public function lockSerials(string $orgId, array $serialNos): Collection
    {
        if (empty($serialNos)) {
            return collect();
        }

        return SerialNumber::where('organization_id', $orgId)
            ->whereIn('serial_no', $serialNos)
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get()
            ->keyBy('serial_no');
    }

    /**
     * Validate and transition serials to ISSUED state.
     */
    public function issueSerials(
        Collection $lockedSerials,
        array $serialNos,
        int $goodsId,
        int $sourceWarehouseId,
        int $sourceLocationId
    ): void {
        foreach ($serialNos as $serialNo) {
            /** @var SerialNumber|null $serial */
            $serial = $lockedSerials->get($serialNo);

            if (!$serial) {
                throw new StockDocumentException(
                    "Serial number '{$serialNo}' not found",
                    'SERIAL_NOT_AVAILABLE',
                    409
                );
            }

            if ($serial->goods_id !== $goodsId) {
                throw new StockDocumentException(
                    "Serial number '{$serialNo}' belongs to different goods",
                    'INVALID_SERIAL_STATE',
                    409
                );
            }

            $currentStatus = $serial->status instanceof SerialNumberStatus ? $serial->status->value : (string) $serial->status;
            if ($currentStatus !== SerialNumberStatus::IN_STOCK->value) {
                throw new StockDocumentException(
                    "Serial number '{$serialNo}' is not available for issue (current status: {$currentStatus})",
                    'INVALID_SERIAL_STATE',
                    409
                );
            }

            if ($serial->warehouse_id !== $sourceWarehouseId || $serial->location_id !== $sourceLocationId) {
                throw new StockDocumentException(
                    "Serial number '{$serialNo}' is located in a different warehouse/location",
                    'SERIAL_NOT_AVAILABLE',
                    409
                );
            }

            $serial->update([
                'status' => SerialNumberStatus::ISSUED,
                'warehouse_id' => null,
                'location_id' => null,
            ]);
        }
    }

    /**
     * Validate and transition serials during TRANSFER.
     */
    public function transferSerials(
        Collection $lockedSerials,
        array $serialNos,
        int $goodsId,
        int $sourceWarehouseId,
        int $sourceLocationId,
        int $destWarehouseId,
        int $destLocationId
    ): void {
        foreach ($serialNos as $serialNo) {
            /** @var SerialNumber|null $serial */
            $serial = $lockedSerials->get($serialNo);

            if (!$serial) {
                throw new StockDocumentException(
                    "Serial number '{$serialNo}' not found",
                    'SERIAL_NOT_AVAILABLE',
                    409
                );
            }

            if ($serial->goods_id !== $goodsId) {
                throw new StockDocumentException(
                    "Serial number '{$serialNo}' belongs to different goods",
                    'INVALID_SERIAL_STATE',
                    409
                );
            }

            $currentStatus = $serial->status instanceof SerialNumberStatus ? $serial->status->value : (string) $serial->status;
            if ($currentStatus !== SerialNumberStatus::IN_STOCK->value) {
                throw new StockDocumentException(
                    "Serial number '{$serialNo}' is not available for transfer (current status: {$currentStatus})",
                    'INVALID_SERIAL_STATE',
                    409
                );
            }

            if ($serial->warehouse_id !== $sourceWarehouseId || $serial->location_id !== $sourceLocationId) {
                throw new StockDocumentException(
                    "Serial number '{$serialNo}' is not in source location for transfer",
                    'SERIAL_NOT_AVAILABLE',
                    409
                );
            }

            $serial->update([
                'warehouse_id' => $destWarehouseId,
                'location_id' => $destLocationId,
                'status' => SerialNumberStatus::IN_STOCK,
            ]);
        }
    }

    /**
     * Reconcile serials for ADJUSTMENT.
     */
    public function adjustSerials(
        string $orgId,
        int $goodsId,
        int $warehouseId,
        int $locationId,
        array $countedSerialNos
    ): array {
        // Find all currently IN_STOCK serials at target location
        $currentSerials = SerialNumber::where('organization_id', $orgId)
            ->where('goods_id', $goodsId)
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->where('status', SerialNumberStatus::IN_STOCK)
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get()
            ->keyBy('serial_no');

        // Also lock all counted serials not in current location
        $allCountedSerials = SerialNumber::where('organization_id', $orgId)
            ->where('goods_id', $goodsId)
            ->whereIn('serial_no', $countedSerialNos)
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get()
            ->keyBy('serial_no');

        $missingSerialNos = [];
        $foundSerialNos = [];

        // Identify missing serials: present in current but absent from counted list
        foreach ($currentSerials as $serialNo => $serial) {
            if (!in_array($serialNo, $countedSerialNos, true)) {
                $serial->update([
                    'status' => SerialNumberStatus::MISSING,
                    'warehouse_id' => null,
                    'location_id' => null,
                ]);
                $missingSerialNos[] = $serialNo;
            }
        }

        // Identify found serials: present in counted list but absent from current location
        foreach ($countedSerialNos as $serialNo) {
            if (!$currentSerials->has($serialNo)) {
                /** @var SerialNumber|null $serial */
                $serial = $allCountedSerials->get($serialNo);

                if (!$serial) {
                    throw new StockDocumentException(
                        "Counted serial '{$serialNo}' does not exist in master data",
                        'INVALID_SERIAL_STATE',
                        409
                    );
                }

                $status = $serial->status instanceof SerialNumberStatus ? $serial->status->value : (string) $serial->status;

                if ($status === SerialNumberStatus::RESERVED->value) {
                    throw new StockDocumentException(
                        "Counted serial '{$serialNo}' is currently RESERVED and cannot be adjusted",
                        'SERIAL_RESERVED',
                        409
                    );
                }

                if ($status === SerialNumberStatus::IN_STOCK->value) {
                    throw new StockDocumentException(
                        "Counted serial '{$serialNo}' is already IN_STOCK in another location. Use TRANSFER instead.",
                        'SERIAL_EXISTS_IN_OTHER_LOCATION',
                        409
                    );
                }

                // Allowed to restore from ISSUED or MISSING
                if (in_array($status, [SerialNumberStatus::ISSUED->value, SerialNumberStatus::MISSING->value], true)) {
                    $serial->update([
                        'warehouse_id' => $warehouseId,
                        'location_id' => $locationId,
                        'status' => SerialNumberStatus::IN_STOCK,
                    ]);
                    $foundSerialNos[] = $serialNo;
                } else {
                    throw new StockDocumentException(
                        "Counted serial '{$serialNo}' has invalid status: {$status}",
                        'INVALID_SERIAL_STATE',
                        409
                    );
                }
            }
        }

        return [
            'missing' => $missingSerialNos,
            'found' => $foundSerialNos,
        ];
    }

    /**
     * Reserve serials: transition IN_STOCK -> RESERVED.
     */
    public function reserveSerials(
        Collection $lockedSerials,
        array $serialNos,
        int $goodsId,
        int $sourceWarehouseId,
        int $sourceLocationId
    ): void {
        foreach ($serialNos as $serialNo) {
            /** @var SerialNumber|null $serial */
            $serial = $lockedSerials->get($serialNo);

            if (!$serial) {
                throw new StockDocumentException(
                    "Serial number '{$serialNo}' not found",
                    'SERIAL_NOT_AVAILABLE',
                    409
                );
            }

            if ($serial->goods_id !== $goodsId) {
                throw new StockDocumentException(
                    "Serial number '{$serialNo}' belongs to different goods",
                    'INVALID_SERIAL_STATE',
                    409
                );
            }

            $currentStatus = $serial->status instanceof SerialNumberStatus ? $serial->status->value : (string) $serial->status;
            if ($currentStatus !== SerialNumberStatus::IN_STOCK->value) {
                throw new StockDocumentException(
                    "Serial number '{$serialNo}' is not available for reservation (current status: {$currentStatus})",
                    'INVALID_SERIAL_STATE',
                    409
                );
            }

            if ($serial->warehouse_id !== $sourceWarehouseId || $serial->location_id !== $sourceLocationId) {
                throw new StockDocumentException(
                    "Serial number '{$serialNo}' is in different warehouse/location",
                    'SERIAL_NOT_AVAILABLE',
                    409
                );
            }

            $serial->update(['status' => SerialNumberStatus::RESERVED]);
        }
    }

    /**
     * Release reserved serials: transition RESERVED -> IN_STOCK.
     */
    public function releaseSerials(
        Collection $lockedSerials,
        array $serialNos,
        int $goodsId,
        int $sourceWarehouseId,
        int $sourceLocationId
    ): void {
        foreach ($serialNos as $serialNo) {
            /** @var SerialNumber|null $serial */
            $serial = $lockedSerials->get($serialNo);

            if (!$serial) {
                throw new StockDocumentException(
                    "Serial number '{$serialNo}' not found",
                    'SERIAL_NOT_AVAILABLE',
                    409
                );
            }

            $currentStatus = $serial->status instanceof SerialNumberStatus ? $serial->status->value : (string) $serial->status;
            if ($currentStatus !== SerialNumberStatus::RESERVED->value) {
                throw new StockDocumentException(
                    "Serial number '{$serialNo}' is not in RESERVED status (current status: {$currentStatus})",
                    'INVALID_SERIAL_STATE',
                    409
                );
            }

            $serial->update(['status' => SerialNumberStatus::IN_STOCK]);
        }
    }

    /**
     * Consume reserved serials during Issue post: transition RESERVED -> ISSUED.
     */
    public function consumeReservedSerials(
        Collection $lockedSerials,
        array $serialNos,
        int $goodsId
    ): void {
        foreach ($serialNos as $serialNo) {
            /** @var SerialNumber|null $serial */
            $serial = $lockedSerials->get($serialNo);

            if (!$serial) {
                throw new StockDocumentException(
                    "Serial number '{$serialNo}' not found",
                    'SERIAL_NOT_AVAILABLE',
                    409
                );
            }

            $currentStatus = $serial->status instanceof SerialNumberStatus ? $serial->status->value : (string) $serial->status;
            if ($currentStatus !== SerialNumberStatus::RESERVED->value) {
                throw new StockDocumentException(
                    "Serial number '{$serialNo}' is not in RESERVED status for consumption (current status: {$currentStatus})",
                    'INVALID_SERIAL_STATE',
                    409
                );
            }

            $serial->update([
                'status' => SerialNumberStatus::ISSUED,
                'warehouse_id' => null,
                'location_id' => null,
            ]);
        }
    }

    /**
     * Reversal of RECEIVE: transition IN_STOCK -> REVERSED.
     */
    public function reverseReceiveSerials(
        Collection $lockedSerials,
        array $serialNos,
        int $goodsId,
        int $origWarehouseId,
        int $origLocationId
    ): void {
        foreach ($serialNos as $serialNo) {
            /** @var SerialNumber|null $serial */
            $serial = $lockedSerials->get($serialNo);

            if (!$serial) {
                throw new StockDocumentException(
                    "Serial number '{$serialNo}' not found for reversal",
                    'SERIAL_NOT_AVAILABLE',
                    409
                );
            }

            $currentStatus = $serial->status instanceof SerialNumberStatus ? $serial->status->value : (string) $serial->status;
            if ($currentStatus !== SerialNumberStatus::IN_STOCK->value ||
                $serial->warehouse_id !== $origWarehouseId ||
                $serial->location_id !== $origLocationId) {
                throw new StockDocumentException(
                    "Serial number '{$serialNo}' cannot be reversed (status: {$currentStatus}, warehouse: {$serial->warehouse_id}, location: {$serial->location_id})",
                    'SERIAL_STATE_CONFLICT',
                    409
                );
            }

            $serial->update([
                'status' => SerialNumberStatus::REVERSED,
                'warehouse_id' => null,
                'location_id' => null,
            ]);
        }
    }

    /**
     * Reversal of ISSUE: transition ISSUED -> IN_STOCK at original location.
     */
    public function reverseIssueSerials(
        Collection $lockedSerials,
        array $serialNos,
        int $goodsId,
        int $origWarehouseId,
        int $origLocationId
    ): void {
        foreach ($serialNos as $serialNo) {
            /** @var SerialNumber|null $serial */
            $serial = $lockedSerials->get($serialNo);

            if (!$serial) {
                throw new StockDocumentException(
                    "Serial number '{$serialNo}' not found for reversal",
                    'SERIAL_NOT_AVAILABLE',
                    409
                );
            }

            $currentStatus = $serial->status instanceof SerialNumberStatus ? $serial->status->value : (string) $serial->status;
            if ($currentStatus !== SerialNumberStatus::ISSUED->value) {
                throw new StockDocumentException(
                    "Serial number '{$serialNo}' cannot be returned to stock because status is {$currentStatus}",
                    'SERIAL_STATE_CONFLICT',
                    409
                );
            }

            $serial->update([
                'status' => SerialNumberStatus::IN_STOCK,
                'warehouse_id' => $origWarehouseId,
                'location_id' => $origLocationId,
            ]);
        }
    }

    /**
     * Reversal of TRANSFER: transition IN_STOCK from destination back to source location.
     */
    public function reverseTransferSerials(
        Collection $lockedSerials,
        array $serialNos,
        int $goodsId,
        int $origSourceWarehouseId,
        int $origSourceLocationId,
        int $origDestWarehouseId,
        int $origDestLocationId
    ): void {
        foreach ($serialNos as $serialNo) {
            /** @var SerialNumber|null $serial */
            $serial = $lockedSerials->get($serialNo);

            if (!$serial) {
                throw new StockDocumentException(
                    "Serial number '{$serialNo}' not found for reversal",
                    'SERIAL_NOT_AVAILABLE',
                    409
                );
            }

            $currentStatus = $serial->status instanceof SerialNumberStatus ? $serial->status->value : (string) $serial->status;
            if ($currentStatus !== SerialNumberStatus::IN_STOCK->value ||
                $serial->warehouse_id !== $origDestWarehouseId ||
                $serial->location_id !== $origDestLocationId) {
                throw new StockDocumentException(
                    "Serial number '{$serialNo}' is not at destination location for reversal (status: {$currentStatus})",
                    'SERIAL_STATE_CONFLICT',
                    409
                );
            }

            $serial->update([
                'status' => SerialNumberStatus::IN_STOCK,
                'warehouse_id' => $origSourceWarehouseId,
                'location_id' => $origSourceLocationId,
            ]);
        }
    }
}
