<x-filament-panels::page>
    @if (! $isConfigured)
        <x-filament::section>
            <x-slot name="heading">Evolution API yapılandırılmamış</x-slot>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                Bağlantı ekranını kullanabilmek için sunucudaki <code>.env</code> dosyasına şu değerleri ekleyin,
                ardından <code>php artisan config:clear</code> çalıştırın:
            </p>

            <pre class="mt-3 rounded-lg bg-gray-950 p-4 text-xs text-gray-100 overflow-x-auto">EVOLUTION_API_URL=http://127.0.0.1:18080
EVOLUTION_API_KEY=&lt;evolution api key&gt;
EVOLUTION_API_INSTANCE=freefcc</pre>
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">Bağlantı Durumu</x-slot>
            <x-slot name="description">Instance: <span class="font-mono">{{ $instanceName }}</span></x-slot>

            <x-slot name="afterHeader">
                <x-filament::badge :color="match ($state) {
                    'open' => 'success',
                    'connecting' => 'warning',
                    'error' => 'danger',
                    default => 'gray',
                }">
                    {{ match ($state) {
                        'open' => 'Bağlı',
                        'connecting' => 'QR bekleniyor',
                        'close' => 'Bağlı değil',
                        'not_found' => 'Instance yok',
                        'error' => 'Hata',
                        default => 'Bilinmiyor',
                    } }}
                </x-filament::badge>
            </x-slot>

            <div wire:poll.5s="refreshStatus">
                @if ($state === 'open')
                    <div class="flex items-center gap-4">
                        @if ($profilePicture)
                            <img src="{{ $profilePicture }}" alt="" class="h-14 w-14 rounded-full object-cover" />
                        @else
                            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                                <x-filament::icon icon="heroicon-o-user" class="h-7 w-7 text-gray-400" />
                            </div>
                        @endif

                        <div>
                            <p class="font-medium text-gray-950 dark:text-white">
                                {{ $profileName ?: 'WhatsApp bağlı' }}
                            </p>
                            @if ($phone)
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $phone }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="mt-4">
                        <x-filament::button color="danger" wire:click="disconnect" wire:confirm="WhatsApp bağlantısını kesmek istediğinize emin misiniz?">
                            Bağlantıyı Kes
                        </x-filament::button>
                    </div>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        WhatsApp hesabınızı bağlamak için aşağıdaki butona basın ve açılan QR kodu telefonunuzdan
                        WhatsApp &rarr; Bağlı Cihazlar &rarr; Cihaz Bağla ile okutun.
                    </p>

                    <div class="mt-4">
                        <x-filament::button wire:click="connect" icon="heroicon-o-qr-code">
                            {{ $qrCode ? 'QR Kodu Yenile' : 'Bağlan / QR Kod Oluştur' }}
                        </x-filament::button>
                    </div>

                    @if ($qrCode)
                        <div class="mt-6 flex flex-col items-center gap-3">
                            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700">
                                <img src="{{ $qrCode }}" alt="WhatsApp QR" class="h-56 w-56" />
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                QR kod okutulduğunda bu sayfa otomatik olarak güncellenir.
                            </p>
                        </div>
                    @endif
                @endif
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
