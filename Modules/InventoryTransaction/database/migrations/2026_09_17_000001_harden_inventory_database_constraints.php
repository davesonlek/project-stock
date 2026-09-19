<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. CHECK constraint: quantity_delta sign and reversal_of pairing
        DB::statement("
            ALTER TABLE stock_movements
            ADD CONSTRAINT stock_movements_quantity_delta_sign_check
            CHECK (
                (
                    movement_type IN ('RECEIVE', 'TRANSFER_IN', 'ADJUST_IN')
                    AND quantity_delta > 0
                    AND reversal_of IS NULL
                )
                OR (
                    movement_type IN ('ISSUE', 'TRANSFER_OUT', 'ADJUST_OUT')
                    AND quantity_delta < 0
                    AND reversal_of IS NULL
                )
                OR (
                    movement_type = 'REVERSAL'
                    AND reversal_of IS NOT NULL
                )
            );
        ");

        // 2. Partial unique index: one reversal movement per original movement
        DB::statement('
            CREATE UNIQUE INDEX idx_stock_movements_reversal_of_unique
            ON stock_movements (reversal_of)
            WHERE reversal_of IS NOT NULL;
        ');

        // 3. Validate stock_movements on INSERT
        DB::unprepared("
            CREATE OR REPLACE FUNCTION fn_validate_stock_movement_insert()
            RETURNS TRIGGER AS \$\$
            DECLARE
                v_orig stock_movements%ROWTYPE;
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM stock_documents d
                    WHERE d.id = NEW.document_id
                      AND d.organization_id = NEW.organization_id
                ) THEN
                    RAISE EXCEPTION 'stock_movements.organization_id (%) does not match stock_documents.organization_id for document_id %',
                        NEW.organization_id, NEW.document_id;
                END IF;

                IF NOT EXISTS (
                    SELECT 1
                    FROM stock_document_lines l
                    WHERE l.id = NEW.document_line_id
                      AND l.document_id = NEW.document_id
                ) THEN
                    RAISE EXCEPTION 'stock_movements.document_line_id (%) is not a line of document_id %',
                        NEW.document_line_id, NEW.document_id;
                END IF;

                IF NOT EXISTS (
                    SELECT 1
                    FROM stock_document_lines l
                    WHERE l.id = NEW.document_line_id
                      AND l.goods_id = NEW.goods_id
                ) THEN
                    RAISE EXCEPTION 'stock_movements.goods_id (%) does not match stock_document_lines.goods_id for document_line_id %',
                        NEW.goods_id, NEW.document_line_id;
                END IF;

                IF NOT EXISTS (
                    SELECT 1
                    FROM goods g
                    WHERE g.id = NEW.goods_id
                      AND g.organization_id = NEW.organization_id
                ) THEN
                    RAISE EXCEPTION 'goods_id % does not belong to organization_id %',
                        NEW.goods_id, NEW.organization_id;
                END IF;

                IF NOT EXISTS (
                    SELECT 1
                    FROM warehouses w
                    WHERE w.id = NEW.warehouse_id
                      AND w.organization_id = NEW.organization_id
                ) THEN
                    RAISE EXCEPTION 'warehouse_id % does not belong to organization_id %',
                        NEW.warehouse_id, NEW.organization_id;
                END IF;

                IF NOT EXISTS (
                    SELECT 1
                    FROM warehouse_locations wl
                    WHERE wl.id = NEW.location_id
                      AND wl.warehouse_id = NEW.warehouse_id
                      AND wl.organization_id = NEW.organization_id
                ) THEN
                    RAISE EXCEPTION 'location_id % is not under warehouse_id % in organization_id %',
                        NEW.location_id, NEW.warehouse_id, NEW.organization_id;
                END IF;

                IF NEW.lot_id IS NOT NULL THEN
                    IF NOT EXISTS (
                        SELECT 1
                        FROM stock_lots sl
                        WHERE sl.id = NEW.lot_id
                          AND sl.organization_id = NEW.organization_id
                          AND sl.goods_id = NEW.goods_id
                    ) THEN
                        RAISE EXCEPTION 'lot_id % is not a lot of goods_id % in organization_id %',
                            NEW.lot_id, NEW.goods_id, NEW.organization_id;
                    END IF;
                END IF;

                IF NEW.movement_type = 'REVERSAL' THEN
                    SELECT * INTO v_orig
                    FROM stock_movements
                    WHERE id = NEW.reversal_of;

                    IF NOT FOUND THEN
                        RAISE EXCEPTION 'Reversal movement references non-existent original movement id %',
                            NEW.reversal_of;
                    END IF;

                    IF v_orig.movement_type = 'REVERSAL' THEN
                        RAISE EXCEPTION 'Cannot reverse a REVERSAL movement (original movement id %)',
                            NEW.reversal_of;
                    END IF;

                    IF v_orig.organization_id IS DISTINCT FROM NEW.organization_id
                       OR v_orig.goods_id IS DISTINCT FROM NEW.goods_id
                       OR v_orig.warehouse_id IS DISTINCT FROM NEW.warehouse_id
                       OR v_orig.location_id IS DISTINCT FROM NEW.location_id
                       OR v_orig.lot_id IS DISTINCT FROM NEW.lot_id
                    THEN
                        RAISE EXCEPTION 'Reversal movement must match original movement organization, goods, warehouse, location, and lot (reversal_of %)',
                            NEW.reversal_of;
                    END IF;

                    IF (NEW.quantity_delta + v_orig.quantity_delta) <> 0 THEN
                        RAISE EXCEPTION 'Reversal quantity_delta (%) must be the negation of original movement quantity_delta (%)',
                            NEW.quantity_delta, v_orig.quantity_delta;
                    END IF;
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_validate_stock_movement_insert
            BEFORE INSERT ON stock_movements
            FOR EACH ROW
            EXECUTE FUNCTION fn_validate_stock_movement_insert();
        ");

        // 4. Validate stock_balances relations
        DB::unprepared("
            CREATE OR REPLACE FUNCTION fn_validate_stock_balance_relations()
            RETURNS TRIGGER AS \$\$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM goods g
                    WHERE g.id = NEW.goods_id
                      AND g.organization_id = NEW.organization_id
                ) THEN
                    RAISE EXCEPTION 'stock_balances.goods_id % does not belong to organization_id %',
                        NEW.goods_id, NEW.organization_id;
                END IF;

                IF NOT EXISTS (
                    SELECT 1
                    FROM warehouses w
                    WHERE w.id = NEW.warehouse_id
                      AND w.organization_id = NEW.organization_id
                ) THEN
                    RAISE EXCEPTION 'stock_balances.warehouse_id % does not belong to organization_id %',
                        NEW.warehouse_id, NEW.organization_id;
                END IF;

                IF NOT EXISTS (
                    SELECT 1
                    FROM warehouse_locations wl
                    WHERE wl.id = NEW.location_id
                      AND wl.warehouse_id = NEW.warehouse_id
                      AND wl.organization_id = NEW.organization_id
                ) THEN
                    RAISE EXCEPTION 'stock_balances.location_id % is not under warehouse_id % in organization_id %',
                        NEW.location_id, NEW.warehouse_id, NEW.organization_id;
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_validate_stock_balance_relations
            BEFORE INSERT OR UPDATE ON stock_balances
            FOR EACH ROW
            EXECUTE FUNCTION fn_validate_stock_balance_relations();
        ");

        // 5. Validate stock_lot_balances relations
        DB::unprepared("
            CREATE OR REPLACE FUNCTION fn_validate_stock_lot_balance_relations()
            RETURNS TRIGGER AS \$\$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM stock_lots sl
                    INNER JOIN goods g ON g.id = sl.goods_id
                    WHERE sl.id = NEW.lot_id
                      AND sl.organization_id = NEW.organization_id
                      AND g.organization_id = NEW.organization_id
                ) THEN
                    RAISE EXCEPTION 'stock_lot_balances.lot_id % is not valid for organization_id %',
                        NEW.lot_id, NEW.organization_id;
                END IF;

                IF NOT EXISTS (
                    SELECT 1
                    FROM warehouses w
                    WHERE w.id = NEW.warehouse_id
                      AND w.organization_id = NEW.organization_id
                ) THEN
                    RAISE EXCEPTION 'stock_lot_balances.warehouse_id % does not belong to organization_id %',
                        NEW.warehouse_id, NEW.organization_id;
                END IF;

                IF NOT EXISTS (
                    SELECT 1
                    FROM warehouse_locations wl
                    WHERE wl.id = NEW.location_id
                      AND wl.warehouse_id = NEW.warehouse_id
                      AND wl.organization_id = NEW.organization_id
                ) THEN
                    RAISE EXCEPTION 'stock_lot_balances.location_id % is not under warehouse_id % in organization_id %',
                        NEW.location_id, NEW.warehouse_id, NEW.organization_id;
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_validate_stock_lot_balance_relations
            BEFORE INSERT OR UPDATE ON stock_lot_balances
            FOR EACH ROW
            EXECUTE FUNCTION fn_validate_stock_lot_balance_relations();
        ");

        // 6. Validate stock_document_lines relations
        DB::unprepared("
            CREATE OR REPLACE FUNCTION fn_validate_stock_document_line_relations()
            RETURNS TRIGGER AS \$\$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM stock_documents d
                    INNER JOIN goods g ON g.id = NEW.goods_id
                    WHERE d.id = NEW.document_id
                      AND g.organization_id = d.organization_id
                ) THEN
                    RAISE EXCEPTION 'stock_document_lines.goods_id % does not belong to the organization of document_id %',
                        NEW.goods_id, NEW.document_id;
                END IF;

                IF NEW.lot_id IS NOT NULL THEN
                    IF NOT EXISTS (
                        SELECT 1
                        FROM stock_documents d
                        INNER JOIN stock_lots sl ON sl.id = NEW.lot_id
                        WHERE d.id = NEW.document_id
                          AND sl.goods_id = NEW.goods_id
                          AND sl.organization_id = d.organization_id
                    ) THEN
                        RAISE EXCEPTION 'stock_document_lines.lot_id % is not a lot of goods_id % in the document organization',
                            NEW.lot_id, NEW.goods_id;
                    END IF;
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_validate_stock_document_line_relations
            BEFORE INSERT OR UPDATE ON stock_document_lines
            FOR EACH ROW
            EXECUTE FUNCTION fn_validate_stock_document_line_relations();
        ");

        // 7. Validate stock_documents relations
        DB::unprepared("
            CREATE OR REPLACE FUNCTION fn_validate_stock_document_relations()
            RETURNS TRIGGER AS \$\$
            BEGIN
                IF NEW.source_warehouse_id IS NOT NULL THEN
                    IF NOT EXISTS (
                        SELECT 1
                        FROM warehouses w
                        WHERE w.id = NEW.source_warehouse_id
                          AND w.organization_id = NEW.organization_id
                    ) THEN
                        RAISE EXCEPTION 'stock_documents.source_warehouse_id % does not belong to organization_id %',
                            NEW.source_warehouse_id, NEW.organization_id;
                    END IF;
                END IF;

                IF NEW.source_location_id IS NOT NULL THEN
                    IF NEW.source_warehouse_id IS NULL THEN
                        RAISE EXCEPTION 'stock_documents.source_location_id requires source_warehouse_id';
                    END IF;

                    IF NOT EXISTS (
                        SELECT 1
                        FROM warehouse_locations wl
                        WHERE wl.id = NEW.source_location_id
                          AND wl.warehouse_id = NEW.source_warehouse_id
                          AND wl.organization_id = NEW.organization_id
                    ) THEN
                        RAISE EXCEPTION 'stock_documents.source_location_id % is not under source_warehouse_id %',
                            NEW.source_location_id, NEW.source_warehouse_id;
                    END IF;
                END IF;

                IF NEW.destination_warehouse_id IS NOT NULL THEN
                    IF NOT EXISTS (
                        SELECT 1
                        FROM warehouses w
                        WHERE w.id = NEW.destination_warehouse_id
                          AND w.organization_id = NEW.organization_id
                    ) THEN
                        RAISE EXCEPTION 'stock_documents.destination_warehouse_id % does not belong to organization_id %',
                            NEW.destination_warehouse_id, NEW.organization_id;
                    END IF;
                END IF;

                IF NEW.destination_location_id IS NOT NULL THEN
                    IF NEW.destination_warehouse_id IS NULL THEN
                        RAISE EXCEPTION 'stock_documents.destination_location_id requires destination_warehouse_id';
                    END IF;

                    IF NOT EXISTS (
                        SELECT 1
                        FROM warehouse_locations wl
                        WHERE wl.id = NEW.destination_location_id
                          AND wl.warehouse_id = NEW.destination_warehouse_id
                          AND wl.organization_id = NEW.organization_id
                    ) THEN
                        RAISE EXCEPTION 'stock_documents.destination_location_id % is not under destination_warehouse_id %',
                            NEW.destination_location_id, NEW.destination_warehouse_id;
                    END IF;
                END IF;

                IF NEW.supplier_id IS NOT NULL THEN
                    IF NOT EXISTS (
                        SELECT 1
                        FROM suppliers s
                        WHERE s.id = NEW.supplier_id
                          AND s.organization_id = NEW.organization_id
                    ) THEN
                        RAISE EXCEPTION 'stock_documents.supplier_id % does not belong to organization_id %',
                            NEW.supplier_id, NEW.organization_id;
                    END IF;
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_validate_stock_document_relations
            BEFORE INSERT OR UPDATE ON stock_documents
            FOR EACH ROW
            EXECUTE FUNCTION fn_validate_stock_document_relations();
        ");

        // 8. Validate stock_reservations relations
        DB::unprepared("
            CREATE OR REPLACE FUNCTION fn_validate_stock_reservation_relations()
            RETURNS TRIGGER AS \$\$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM goods g
                    WHERE g.id = NEW.goods_id
                      AND g.organization_id = NEW.organization_id
                ) THEN
                    RAISE EXCEPTION 'stock_reservations.goods_id % does not belong to organization_id %',
                        NEW.goods_id, NEW.organization_id;
                END IF;

                IF NOT EXISTS (
                    SELECT 1
                    FROM warehouses w
                    WHERE w.id = NEW.warehouse_id
                      AND w.organization_id = NEW.organization_id
                ) THEN
                    RAISE EXCEPTION 'stock_reservations.warehouse_id % does not belong to organization_id %',
                        NEW.warehouse_id, NEW.organization_id;
                END IF;

                IF NOT EXISTS (
                    SELECT 1
                    FROM warehouse_locations wl
                    WHERE wl.id = NEW.location_id
                      AND wl.warehouse_id = NEW.warehouse_id
                      AND wl.organization_id = NEW.organization_id
                ) THEN
                    RAISE EXCEPTION 'stock_reservations.location_id % is not under warehouse_id % in organization_id %',
                        NEW.location_id, NEW.warehouse_id, NEW.organization_id;
                END IF;

                IF NEW.lot_id IS NOT NULL THEN
                    IF NOT EXISTS (
                        SELECT 1
                        FROM stock_lots sl
                        WHERE sl.id = NEW.lot_id
                          AND sl.organization_id = NEW.organization_id
                          AND sl.goods_id = NEW.goods_id
                    ) THEN
                        RAISE EXCEPTION 'stock_reservations.lot_id % is not a lot of goods_id % in organization_id %',
                            NEW.lot_id, NEW.goods_id, NEW.organization_id;
                    END IF;
                END IF;

                IF NEW.document_id IS NOT NULL THEN
                    IF NOT EXISTS (
                        SELECT 1
                        FROM stock_documents sd
                        WHERE sd.id = NEW.document_id
                          AND sd.organization_id = NEW.organization_id
                    ) THEN
                        RAISE EXCEPTION 'stock_reservations.document_id % does not belong to organization_id %',
                            NEW.document_id, NEW.organization_id;
                    END IF;
                END IF;

                IF NEW.document_line_id IS NOT NULL THEN
                    IF NEW.document_id IS NOT NULL THEN
                        IF NOT EXISTS (
                            SELECT 1
                            FROM stock_document_lines sdl
                            WHERE sdl.id = NEW.document_line_id
                              AND sdl.document_id = NEW.document_id
                              AND sdl.goods_id = NEW.goods_id
                        ) THEN
                            RAISE EXCEPTION 'stock_reservations.document_line_id % is not a line of document_id % with goods_id %',
                                NEW.document_line_id, NEW.document_id, NEW.goods_id;
                        END IF;
                    ELSE
                        IF NOT EXISTS (
                            SELECT 1
                            FROM stock_document_lines sdl
                            INNER JOIN stock_documents sd ON sd.id = sdl.document_id
                            WHERE sdl.id = NEW.document_line_id
                              AND sdl.goods_id = NEW.goods_id
                              AND sd.organization_id = NEW.organization_id
                        ) THEN
                            RAISE EXCEPTION 'stock_reservations.document_line_id % does not match goods_id % in organization_id %',
                                NEW.document_line_id, NEW.goods_id, NEW.organization_id;
                        END IF;
                    END IF;
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_validate_stock_reservation_relations
            BEFORE INSERT OR UPDATE ON stock_reservations
            FOR EACH ROW
            EXECUTE FUNCTION fn_validate_stock_reservation_relations();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("
            DROP TRIGGER IF EXISTS trg_validate_stock_reservation_relations ON stock_reservations;
            DROP FUNCTION IF EXISTS fn_validate_stock_reservation_relations();

            DROP TRIGGER IF EXISTS trg_validate_stock_document_relations ON stock_documents;
            DROP FUNCTION IF EXISTS fn_validate_stock_document_relations();

            DROP TRIGGER IF EXISTS trg_validate_stock_document_line_relations ON stock_document_lines;
            DROP FUNCTION IF EXISTS fn_validate_stock_document_line_relations();

            DROP TRIGGER IF EXISTS trg_validate_stock_lot_balance_relations ON stock_lot_balances;
            DROP FUNCTION IF EXISTS fn_validate_stock_lot_balance_relations();

            DROP TRIGGER IF EXISTS trg_validate_stock_balance_relations ON stock_balances;
            DROP FUNCTION IF EXISTS fn_validate_stock_balance_relations();

            DROP TRIGGER IF EXISTS trg_validate_stock_movement_insert ON stock_movements;
            DROP FUNCTION IF EXISTS fn_validate_stock_movement_insert();
        ");

        DB::statement('DROP INDEX IF EXISTS idx_stock_movements_reversal_of_unique;');

        DB::statement('
            ALTER TABLE stock_movements
            DROP CONSTRAINT IF EXISTS stock_movements_quantity_delta_sign_check;
        ');
    }
};
