<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        match (DB::getDriverName()) {
            'mysql' => $this->createMysqlTriggers(),
            'pgsql' => $this->createPostgresTrigger(),
            'sqlite' => $this->createSqliteTriggers(),
            default => null,
        };
    }

    public function down(): void
    {
        match (DB::getDriverName()) {
            'mysql' => $this->dropMysqlTriggers(),
            'pgsql' => $this->dropPostgresTrigger(),
            'sqlite' => $this->dropSqliteTriggers(),
            default => null,
        };
    }

    private function createMysqlTriggers(): void
    {
        DB::unprepared("CREATE TRIGGER pagos_venta_no_update BEFORE UPDATE ON pagos_venta FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los pagos de una venta son inmutables'");
        DB::unprepared("CREATE TRIGGER pagos_venta_no_delete BEFORE DELETE ON pagos_venta FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los pagos de una venta son inmutables'");
    }

    private function dropMysqlTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS pagos_venta_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS pagos_venta_no_delete');
    }

    private function createPostgresTrigger(): void
    {
        DB::unprepared("CREATE FUNCTION prevent_sale_payment_mutation() RETURNS trigger AS $$ BEGIN RAISE EXCEPTION 'Los pagos de una venta son inmutables'; END; $$ LANGUAGE plpgsql");
        DB::unprepared('CREATE TRIGGER pagos_venta_no_mutation BEFORE UPDATE OR DELETE ON pagos_venta FOR EACH ROW EXECUTE FUNCTION prevent_sale_payment_mutation()');
    }

    private function dropPostgresTrigger(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS pagos_venta_no_mutation ON pagos_venta');
        DB::unprepared('DROP FUNCTION IF EXISTS prevent_sale_payment_mutation()');
    }

    private function createSqliteTriggers(): void
    {
        DB::unprepared("CREATE TRIGGER pagos_venta_no_update BEFORE UPDATE ON pagos_venta BEGIN SELECT RAISE(ABORT, 'Los pagos de una venta son inmutables'); END");
        DB::unprepared("CREATE TRIGGER pagos_venta_no_delete BEFORE DELETE ON pagos_venta BEGIN SELECT RAISE(ABORT, 'Los pagos de una venta son inmutables'); END");
    }

    private function dropSqliteTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS pagos_venta_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS pagos_venta_no_delete');
    }
};
