<x-layouts.app title="Painel">
    <div class="mb-6">
        <h1 class="font-sans text-[20px] font-bold tracking-[-0.02em] text-ink">Painel da empresa</h1>
        <p class="mt-1 font-sans text-[13px] text-ink-3">Bem-vindo. Conecte seu CRM para ativar o agente.</p>
    </div>

    <x-card title="CRM">
        <x-slot:header>
            <span class="font-sans text-[13.5px] font-semibold text-ink">Conexão do CRM</span>
        </x-slot:header>

        <p>Nenhum CRM conectado ainda.</p>
    </x-card>
</x-layouts.app>
