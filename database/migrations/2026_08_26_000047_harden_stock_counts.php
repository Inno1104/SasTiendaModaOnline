<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_conteos', function (Blueprint $table) {
            $table->uuid('operacion_id')->nullable()->after('motivo');
            $table->char('operacion_hash', 64)->nullable()->after('operacion_id');
            $table->foreignId('reemplazado_por_conteo_id')->nullable()->after('conciliado_at')
                ->constrained('stock_conteos')->restrictOnDelete();
            $table->foreignId('reemplazado_por_usuario_id')->nullable()->after('reemplazado_por_conteo_id')
                ->constrained('users')->restrictOnDelete();
            $table->timestamp('reemplazado_at')->nullable()->after('reemplazado_por_usuario_id');
            $table->unique(['tienda_id', 'operacion_id'], 'conteos_tienda_operacion_unica');
        });

        $this->backfillIdentityAndReplacementTrace();
        $this->assertExistingDataIsValid();

        match (DB::getDriverName()) {
            'mysql' => $this->createMysqlProtections(),
            'pgsql' => $this->createPostgresProtections(),
            'sqlite' => $this->createSqliteProtections(),
            default => null,
        };
    }

    public function down(): void
    {
        match (DB::getDriverName()) {
            'mysql' => $this->dropMysqlProtections(),
            'pgsql' => $this->dropPostgresProtections(),
            'sqlite' => $this->dropSqliteProtections(),
            default => null,
        };

        Schema::table('stock_conteos', function (Blueprint $table) {
            $table->dropUnique('conteos_tienda_operacion_unica');
            $table->dropConstrainedForeignId('reemplazado_por_conteo_id');
            $table->dropConstrainedForeignId('reemplazado_por_usuario_id');
            $table->dropColumn(['operacion_id', 'operacion_hash', 'reemplazado_at']);
        });
    }

    private function backfillIdentityAndReplacementTrace(): void
    {
        DB::table('stock_conteos')->orderBy('id')->each(function (object $count): void {
            DB::table('stock_conteos')->where('id', $count->id)->update([
                'operacion_id' => (string) Str::uuid(),
                'operacion_hash' => hash('sha256', 'legacy-stock-count:'.$count->id),
            ]);
        });

        DB::table('stock_conteos')->where('estado', 'reemplazado')->orderBy('id')
            ->each(function (object $count): void {
                $replacement = DB::table('stock_conteos')
                    ->where('tienda_id', $count->tienda_id)
                    ->where('variante_id', $count->variante_id)
                    ->where('id', '>', $count->id)
                    ->orderBy('id')
                    ->first();
                if (! $replacement) {
                    throw new RuntimeException(
                        "El conteo reemplazado #{$count->id} no tiene un conteo posterior asociado."
                    );
                }

                DB::table('stock_conteos')->where('id', $count->id)->update([
                    'reemplazado_por_conteo_id' => $replacement->id,
                    'reemplazado_por_usuario_id' => $replacement->contado_por,
                    'reemplazado_at' => $replacement->created_at,
                ]);
            });
    }

    private function assertExistingDataIsValid(): void
    {
        if (DB::table('stock_conteos')->whereRaw('NOT ('.$this->integrityExpression().')')->exists()) {
            throw new RuntimeException(
                'Existen conteos de stock inconsistentes. Corrija los datos antes de aplicar las restricciones.'
            );
        }
    }

    /** @return array<string, string> */
    private function checks(string $prefix = ''): array
    {
        $state = $prefix.'estado';
        $system = $prefix.'stock_sistema';
        $physical = $prefix.'stock_fisico';
        $difference = $prefix.'diferencia';
        $authorizedBy = $prefix.'autorizado_por';
        $reconciledAt = $prefix.'conciliado_at';
        $replacement = $prefix.'reemplazado_por_conteo_id';
        $replacementUser = $prefix.'reemplazado_por_usuario_id';
        $replacedAt = $prefix.'reemplazado_at';
        $operationId = $prefix.'operacion_id';
        $operationHash = $prefix.'operacion_hash';
        $signedType = match (DB::getDriverName()) {
            'pgsql' => 'BIGINT',
            'sqlite' => 'INTEGER',
            default => 'SIGNED',
        };

        return [
            'stock_conteos_estado_valido' => "{$state} IN ('pendiente','conciliado','reemplazado')",
            'stock_conteos_diferencia_valida' => "CAST({$physical} AS {$signedType}) - CAST({$system} AS {$signedType}) = {$difference}",
            'stock_conteos_identidad_valida' => "{$operationId} IS NOT NULL AND {$operationHash} IS NOT NULL",
            'stock_conteos_estado_metadata_valida' => "(({$state} = 'pendiente' AND {$authorizedBy} IS NULL AND {$reconciledAt} IS NULL AND {$replacement} IS NULL AND {$replacementUser} IS NULL AND {$replacedAt} IS NULL) OR ({$state} = 'conciliado' AND {$authorizedBy} IS NOT NULL AND {$reconciledAt} IS NOT NULL AND {$replacement} IS NULL AND {$replacementUser} IS NULL AND {$replacedAt} IS NULL) OR ({$state} = 'reemplazado' AND {$authorizedBy} IS NULL AND {$reconciledAt} IS NULL AND {$replacement} IS NOT NULL AND {$replacementUser} IS NOT NULL AND {$replacedAt} IS NOT NULL))",
        ];
    }

    private function integrityExpression(string $prefix = ''): string
    {
        return implode(' AND ', array_map(fn (string $check) => "({$check})", $this->checks($prefix)));
    }

    private function createMysqlProtections(): void
    {
        foreach ($this->checks() as $name => $expression) {
            DB::statement("ALTER TABLE stock_conteos ADD CONSTRAINT {$name} CHECK ({$expression})");
        }
        DB::unprepared("CREATE TRIGGER stock_conteos_terminal_no_update BEFORE UPDATE ON stock_conteos FOR EACH ROW BEGIN IF OLD.estado IN ('conciliado','reemplazado') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los conteos finalizados son inmutables'; END IF; END");
        DB::unprepared("CREATE TRIGGER stock_conteos_terminal_no_delete BEFORE DELETE ON stock_conteos FOR EACH ROW BEGIN IF OLD.estado IN ('conciliado','reemplazado') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los conteos finalizados son inmutables'; END IF; END");
    }

    private function dropMysqlProtections(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS stock_conteos_terminal_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS stock_conteos_terminal_no_delete');
        foreach (array_reverse(array_keys($this->checks())) as $name) {
            DB::statement("ALTER TABLE stock_conteos DROP CHECK {$name}");
        }
    }

    private function createSqliteProtections(): void
    {
        $integrity = $this->integrityExpression('NEW.');
        DB::unprepared("CREATE TRIGGER stock_conteos_integridad_insert BEFORE INSERT ON stock_conteos WHEN NOT ({$integrity}) BEGIN SELECT RAISE(ABORT, 'Conteo de stock inconsistente'); END");
        DB::unprepared("CREATE TRIGGER stock_conteos_integridad_update BEFORE UPDATE ON stock_conteos WHEN NOT ({$integrity}) BEGIN SELECT RAISE(ABORT, 'Conteo de stock inconsistente'); END");
        DB::unprepared("CREATE TRIGGER stock_conteos_terminal_no_update BEFORE UPDATE ON stock_conteos WHEN OLD.estado IN ('conciliado','reemplazado') BEGIN SELECT RAISE(ABORT, 'Los conteos finalizados son inmutables'); END");
        DB::unprepared("CREATE TRIGGER stock_conteos_terminal_no_delete BEFORE DELETE ON stock_conteos WHEN OLD.estado IN ('conciliado','reemplazado') BEGIN SELECT RAISE(ABORT, 'Los conteos finalizados son inmutables'); END");
    }

    private function dropSqliteProtections(): void
    {
        foreach (['stock_conteos_integridad_insert', 'stock_conteos_integridad_update', 'stock_conteos_terminal_no_update', 'stock_conteos_terminal_no_delete'] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }

    private function createPostgresProtections(): void
    {
        foreach ($this->checks() as $name => $expression) {
            DB::statement("ALTER TABLE stock_conteos ADD CONSTRAINT {$name} CHECK ({$expression})");
        }
        DB::unprepared("CREATE FUNCTION prevent_terminal_stock_count_mutation() RETURNS trigger AS $$ BEGIN IF OLD.estado IN ('conciliado','reemplazado') THEN RAISE EXCEPTION 'Los conteos finalizados son inmutables'; END IF; IF TG_OP = 'DELETE' THEN RETURN OLD; END IF; RETURN NEW; END; $$ LANGUAGE plpgsql");
        DB::unprepared('CREATE TRIGGER stock_conteos_terminal_no_update BEFORE UPDATE ON stock_conteos FOR EACH ROW EXECUTE FUNCTION prevent_terminal_stock_count_mutation()');
        DB::unprepared('CREATE TRIGGER stock_conteos_terminal_no_delete BEFORE DELETE ON stock_conteos FOR EACH ROW EXECUTE FUNCTION prevent_terminal_stock_count_mutation()');
    }

    private function dropPostgresProtections(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS stock_conteos_terminal_no_update ON stock_conteos');
        DB::unprepared('DROP TRIGGER IF EXISTS stock_conteos_terminal_no_delete ON stock_conteos');
        DB::unprepared('DROP FUNCTION IF EXISTS prevent_terminal_stock_count_mutation()');
        foreach (array_reverse(array_keys($this->checks())) as $name) {
            DB::statement("ALTER TABLE stock_conteos DROP CONSTRAINT {$name}");
        }
    }
};
