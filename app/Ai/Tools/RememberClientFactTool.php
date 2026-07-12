<?php

namespace App\Ai\Tools;

use App\Services\Memory\Mem0Exception;
use App\Services\Memory\MemoryCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * Store one durable negotiation fact about the current client in the agent's
 * own per-client memory (mem0). This is distinct from a CRM note: the CRM note
 * is written for the human sales team, while this memory is the agent's own
 * working context, recalled on later turns so it never repeats an argument,
 * re-explains a feature or forgets a stated objection — without pushing the raw
 * chat history back into the model's context every time.
 */
class RememberClientFactTool extends ClientMemoryTool
{
    /**
     * Returned when the fact was stored.
     */
    private const SavedMarker = 'Fato registrado na memória deste cliente.';

    public function description(): string
    {
        $categories = collect(MemoryCategory::cases())
            ->map(fn (MemoryCategory $case): string => "\"{$case->value}\" ({$case->label()})")
            ->implode(', ');

        return <<<DESCRIPTION
            Registra na sua memória de longo prazo um fato relevante da negociação com ESTE cliente, para você lembrar em conversas futuras. Serve para você não repetir argumentos, não reexplicar recursos já informados e não esquecer objeções já declaradas.

            Use sempre que, ao longo da conversa, aparecer algo que valha a pena lembrar depois: uma objeção declarada, um argumento de venda que você já usou, um recurso do produto que você já apresentou, orçamento, prazo, quem decide a compra, uma preferência, uma restrição ou a dor que motiva o interesse. Registre em segundo plano: não peça permissão nem avise o cliente.

            Esta memória é sua e serve para personalizar o atendimento; ela NÃO substitui a anotação no CRM (que é para a equipe humana). Não registre saudações, conversa fiada, nem dados que outras ferramentas já fornecem ao vivo (valor, estágio, status do negócio).

            Escreva "content" como uma frase objetiva em terceira pessoa (ex.: "Cliente achou o preço alto e comparou com o concorrente X."). Classifique em "category" quando possível. Valores de category: {$categories}. O cliente é identificado automaticamente pela conversa; não há outro parâmetro.
            DESCRIPTION;
    }

    public function handle(Request $request): string
    {
        if (! $this->memory()->enabled()) {
            return self::DisabledMarker;
        }

        $content = $request['content'] ?? null;
        $content = is_string($content) ? trim($content) : '';

        if ($content === '') {
            return self::FailureMarker;
        }

        $category = MemoryCategory::tryFrom((string) ($request['category'] ?? ''));

        try {
            $this->memory()->add($content, $this->userId(), $this->agentId(), $category);
        } catch (Mem0Exception) {
            return self::FailureMarker;
        }

        return self::SavedMarker;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'content' => $schema->string()
                ->description('O fato a lembrar, como uma frase objetiva em terceira pessoa. Obrigatório.')
                ->required(),
            'category' => $schema->string()
                ->enum(MemoryCategory::values())
                ->description('Categoria do fato, quando aplicável. Opcional.'),
        ];
    }
}
