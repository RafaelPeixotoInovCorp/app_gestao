<?php

namespace App\Support;

enum ProposalStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Sent => 'Enviada',
            self::Pending => 'Pendente',
            self::Accepted => 'Aceite',
            self::Rejected => 'Recusada',
            self::Expired => 'Expirada',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
