<x-app-layout>

<div class="p-4 sm:p-6 lg:p-8 space-y-6 bg-canvas">

    {{-- Header --}}
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-ink">Vendor Performance Clustering</h1>
            <p class="mt-1 text-sm text-muted">
                K-Means k={{ \App\Services\VendorClusteringService::CLUSTER_COUNT }} pada Z-score total shipment &amp; kepatuhan SLA —
                {{ number_format($method['measured_count'], 0, ',', '.') }} vendor masuk clustering,
                {{ count($undecided) }} vendor belum cukup data.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('dashboard') }}"
               class="px-3 py-1.5 rounded-lg text-sm font-semibold text-muted border border-border hover:bg-surface-subtle hover:text-ink transition">
                ← Dashboard
            </a>
            <a href="{{ route('shipments') }}"
               class="px-3 py-1.5 rounded-lg text-sm font-semibold text-muted border border-border hover:bg-surface-subtle hover:text-ink transition">
                Data Pengiriman
            </a>
        </div>
    </div>

    {{-- Cluster cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        @forelse ($clusters as $cluster)
            @php
                $palette = [
                    'Elite' => [
                        'card' => 'border-emerald-200 dark:border-emerald-900',
                        'dot' => 'bg-emerald-500 dark:bg-emerald-400',
                        'text' => 'text-emerald-700 dark:text-emerald-300',
                        'bar' => 'bg-emerald-500 dark:bg-emerald-400',
                    ],
                    'Standar' => [
                        'card' => 'border-blue-200 dark:border-blue-900',
                        'dot' => 'bg-blue-500 dark:bg-blue-400',
                        'text' => 'text-blue-700 dark:text-blue-300',
                        'bar' => 'bg-blue-500 dark:bg-blue-400',
                    ],
                    'Berisiko' => [
                        'card' => 'border-red-200 dark:border-red-900',
                        'dot' => 'bg-red-500 dark:bg-red-400',
                        'text' => 'text-red-700 dark:text-red-300',
                        'bar' => 'bg-red-500 dark:bg-red-400',
                    ],
                ];
                $colors = $palette[$cluster['label']] ?? $palette['Standar'];
            @endphp
            <div class="bg-surface rounded-xl shadow-sm border border-border p-5 {{ $colors['card'] }}">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <span class="w-2.5 h-2.5 rounded-full {{ $colors['dot'] }}"></span>
                        <h2 class="text-lg font-bold text-ink">{{ $cluster['label'] }}</h2>
                    </div>
                    <span class="text-xs font-semibold {{ $colors['text'] }}">
                        {{ $cluster['vendor_count'] }} vendor
                    </span>
                </div>

                <p class="mt-3 text-3xl font-bold text-ink">
                    {{ $cluster['sla_success_rate'] !== null ? number_format($cluster['sla_success_rate'], 1, ',', '.') . '%' : '—' }}
                </p>
                <p class="text-xs text-muted">rata-rata SLA success</p>

                <div class="mt-3 h-1.5 rounded-full bg-surface-subtle overflow-hidden">
                    <div class="h-full rounded-full {{ $colors['bar'] }}"
                         style="width: {{ (int) round($cluster['sla_success_rate'] ?? 0) }}%"></div>
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-3">
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted">Total Resi</dt>
                        <dd class="text-sm font-semibold text-ink">{{ number_format($cluster['total_shipment'], 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted">Rata-rata Aging</dt>
                        <dd class="text-sm font-semibold text-ink">
                            {{ $cluster['avg_aging'] !== null ? number_format($cluster['avg_aging'], 1, ',', '.') . ' hari' : '—' }}
                        </dd>
                    </div>
                </dl>

                <div class="mt-4 pt-3 border-t border-border">
                    <p class="text-[11px] uppercase tracking-wider text-muted mb-1.5">Vendor</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($cluster['members'] as $member)
                            <a href="{{ route('shipments', ['vendor' => $member['vendor']]) }}"
                               title="SLA {{ number_format($member['sla_success_rate'], 1, ',', '.') }}% · {{ number_format($member['total_shipment'], 0, ',', '.') }} resi"
                               class="px-2 py-0.5 rounded-md text-[11px] font-semibold bg-surface-subtle text-muted hover:text-ink hover:bg-slate-200 dark:hover:bg-slate-700 transition">
                                {{ $member['vendor'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @empty
            <div class="md:col-span-3 bg-surface rounded-xl shadow-sm border border-border p-6 text-center text-muted">
                Belum ada data vendor untuk di-cluster.
            </div>
        @endforelse
    </div>

    {{-- Belum cukup data --}}
    @if (count($undecided) > 0)
        <div class="bg-surface rounded-xl shadow-sm border border-border p-5">
            <div class="flex items-start gap-3">
                <div class="p-2 rounded-lg bg-amber-500/10 dark:bg-amber-950/60">
                    <svg class="w-5 h-5 text-amber-600 dark:text-amber-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <h2 class="text-base font-bold text-ink">Belum Cukup Data ({{ count($undecided) }} vendor)</h2>
                    <p class="mt-0.5 text-sm text-muted">
                        Sampel SLA di bawah {{ number_format(\App\Services\VendorClusteringService::MIN_SLA_SAMPLE, 0, ',', '.') }} baris terukur.
                        Persentase pada volume rendah terlalu mudah berubah (satu resi Out SLA bisa menggesernya puluhan poin),
                        jadi vendor ini tidak diberi label cluster.
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($undecided as $row)
                            <a href="{{ route('shipments', ['vendor' => $row['vendor']]) }}"
                               class="px-3 py-1.5 rounded-lg border border-border bg-surface text-sm hover:bg-surface-subtle transition">
                                <span class="font-semibold text-ink">{{ $row['vendor'] }}</span>
                                <span class="text-muted">
                                    · {{ number_format($row['total_shipment'], 0, ',', '.') }} resi
                                    @if ($row['sla_success_rate'] !== null)
                                        · SLA {{ number_format($row['sla_success_rate'], 1, ',', '.') }}%
                                    @endif
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Tabel vendor --}}
    @php
        $ordered = collect($clusters)
            ->flatMap(fn (array $cluster) => collect($cluster['members'])->map(fn (array $member) => [
                'vendor' => $member['vendor'],
                'total_shipment' => $member['total_shipment'],
                'avg_aging' => $member['avg_aging'],
                'sla_success_rate' => $member['sla_success_rate'],
                'cluster' => $cluster['label'],
                'position' => $cluster['position'],
            ]))
            ->sortBy([
                ['position', 'asc'],
                ['sla_success_rate', 'desc'],
                ['vendor', 'asc'],
            ])
            ->values();

        $badge = [
            'Elite' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300',
            'Standar' => 'bg-blue-100 text-blue-700 dark:bg-blue-950/80 dark:text-blue-300',
            'Berisiko' => 'bg-red-100 text-red-700 dark:bg-red-950/80 dark:text-red-300',
        ];
    @endphp

    <div class="bg-surface rounded-xl shadow-sm border border-border overflow-hidden">
        <div class="px-6 py-5 border-b border-border">
            <h2 class="text-lg font-bold text-ink">Kinerja Vendor per Cluster</h2>
            <p class="text-sm text-muted">
                Diurutkan berdasarkan cluster (Elite → Berisiko), lalu SLA success tertinggi.
                Aging ditampilkan sebagai informasi, tetapi sengaja tidak dipakai dalam clustering.
            </p>
        </div>
        <div class="overflow-x-auto dark:bg-surface">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="bg-surface-subtle text-xs uppercase tracking-wider text-muted border-b border-border">
                        <th class="px-6 py-3 font-semibold">Vendor</th>
                        <th class="px-6 py-3 font-semibold text-right">Total Resi</th>
                        <th class="px-6 py-3 font-semibold text-right">Rata-rata Aging</th>
                        <th class="px-6 py-3 font-semibold text-right">SLA Success</th>
                        <th class="px-6 py-3 font-semibold">Cluster</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/70">
                    @forelse ($ordered as $row)
                        <tr class="hover:bg-surface-subtle transition">
                            <td class="px-6 py-3.5 font-semibold text-ink">{{ $row['vendor'] }}</td>
                            <td class="px-6 py-3.5 text-right text-muted">{{ number_format($row['total_shipment'], 0, ',', '.') }}</td>
                            <td class="px-6 py-3.5 text-right text-muted">
                                {{ $row['avg_aging'] !== null ? number_format($row['avg_aging'], 1, ',', '.') . ' hari' : '—' }}
                            </td>
                            <td class="px-6 py-3.5 text-right">
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold
                                    {{ ($row['sla_success_rate'] ?? 0) >= 80 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300' : (($row['sla_success_rate'] ?? 0) >= 60 ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/80 dark:text-amber-300' : 'bg-red-100 text-red-700 dark:bg-red-950/80 dark:text-red-300') }}">
                                    {{ $row['sla_success_rate'] !== null ? number_format($row['sla_success_rate'], 1, ',', '.') . '%' : '—' }}
                                </span>
                            </td>
                            <td class="px-6 py-3.5">
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $badge[$row['cluster']] ?? $badge['Standar'] }}">
                                    {{ $row['cluster'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-muted">Belum ada data vendor.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Catatan keterbatasan metode --}}
    <div class="bg-surface rounded-xl shadow-sm border border-border p-5">
        <div class="flex items-start gap-3">
            <div class="p-2 rounded-lg bg-slate-500/10 dark:bg-slate-500/20">
                <svg class="w-5 h-5 text-slate-600 dark:text-slate-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                </svg>
            </div>
            <div class="text-sm text-muted space-y-2">
                <p class="font-semibold text-ink">Catatan metode</p>
                <ul class="list-disc pl-5 space-y-1">
                    <li>
                        Fitur yang dipakai: <span class="font-semibold text-ink">total shipment</span> dan
                        <span class="font-semibold text-ink">SLA success rate</span>, dinormalisasi dengan Z-score
                        (standard deviation populasi), lalu dikelompokkan K-Means k={{ \App\Services\VendorClusteringService::CLUSTER_COUNT }}
                        dengan inisialisasi deterministik.
                    </li>
                    <li>
                        <span class="font-semibold text-ink">Rata-rata aging tidak dipakai</span> sebagai fitur karena nilainya
                        nyaris konstan per vendor — selisih hanya 2–3 hari untuk ribuan baris — sehingga tidak mencerminkan
                        kondisi per kiriman.
                    </li>
                    <li>
                        <span class="font-semibold text-ink">SLA success rate</span> dihitung dari baris
                        <code class="px-1 rounded bg-surface-subtle">Meet SLA</code> dibagi baris
                        <code class="px-1 rounded bg-surface-subtle">Meet SLA</code> +
                        <code class="px-1 rounded bg-surface-subtle">Out SLA</code>, bukan seluruh jumlah kiriman,
                        agar baris SLA tanpa nilai tidak menurunkan persentase.
                    </li>
                    <li>
                        Label <span class="font-semibold text-ink">Elite / Standar / Berisiko</span> ditetapkan dari urutan
                        kualitas hasil (SLA tertinggi → terendah), bukan dari nomor cluster K-Means yang tidak bermakna.
                    </li>
                    <li>
                        Vendor dengan sampel SLA di bawah {{ number_format(\App\Services\VendorClusteringService::MIN_SLA_SAMPLE, 0, ',', '.') }}
                        baris tidak di-cluster (lihat kotak "Belum Cukup Data").
                    </li>
                </ul>
            </div>
        </div>
    </div>

</div>

</x-app-layout>
