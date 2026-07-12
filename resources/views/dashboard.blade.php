<x-layouts.app title="Painel">
    <div class="mb-6">
        <h1 class="font-sans text-[20px] font-bold tracking-[-0.02em] text-ink">Painel da empresa</h1>
        <p class="mt-1 font-sans text-[13px] text-ink-3">Bem-vindo. Conecte seu CRM para ativar o agente.</p>
    </div>

    <livewire:crm.connection-status />

    <div class="mt-6">
        <livewire:crm.scan-card />
    </div>

    <div class="mt-6">
        <livewire:agent.settings />
    </div>
</x-layouts.app>
