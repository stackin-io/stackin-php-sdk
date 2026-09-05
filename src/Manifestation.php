<?php

declare(strict_types=1);

namespace Stackin;

/**
 * The recipient's four possible answers to a document issued against it.
 */
enum Manifestation: string
{
    case CONFIRMACAO = '210200';
    case CIENCIA = '210210';
    case DESCONHECIMENTO = '210220';
    case OPERACAO_NAO_REALIZADA = '210240';
}
