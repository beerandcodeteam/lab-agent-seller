<?php

namespace App\Services\Memory;

/**
 * The kinds of durable negotiation facts the sales agent stores per client in
 * mem0. The value is persisted as the memory's `category` metadata so future
 * recalls can be reasoned about; the enum keeps the LLM-facing vocabulary
 * closed instead of letting the model invent arbitrary tags.
 */
enum MemoryCategory: string
{
    case Objection = 'objecao';
    case ArgumentUsed = 'argumento_utilizado';
    case FeatureInformed = 'recurso_informado';
    case Budget = 'orcamento';
    case Timeline = 'prazo';
    case DecisionMaker = 'decisor';
    case Preference = 'preferencia';
    case PainPoint = 'dor';
    case Other = 'outro';

    /**
     * The backing values, for the tool input schema's enum constraint.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }

    /**
     * A short PT-BR gloss of each category, used to describe the allowed values
     * to the model in the tool description.
     */
    public function label(): string
    {
        return match ($this) {
            self::Objection => 'objeção declarada pelo cliente',
            self::ArgumentUsed => 'argumento de venda que você já usou com este cliente',
            self::FeatureInformed => 'recurso do produto que você já informou',
            self::Budget => 'orçamento ou faixa de preço do cliente',
            self::Timeline => 'prazo ou urgência da decisão',
            self::DecisionMaker => 'quem decide ou influencia a compra',
            self::Preference => 'preferência ou restrição do cliente',
            self::PainPoint => 'dor ou necessidade que motiva o interesse',
            self::Other => 'outro fato relevante da negociação',
        };
    }
}
