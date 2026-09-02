<?php

namespace App\Support;

final class DocumentNumbering
{
    /**
     * La numeración es continua por tienda, tipo y serie. No se reinicia al
     * cambiar de año; cambiar la serie inicia una secuencia independiente.
     */
    public const POLICY = 'continuous';

    public const CORRELATIVE_LENGTH = 8;

    public const MAX_CORRELATIVE = 99_999_999;
}
