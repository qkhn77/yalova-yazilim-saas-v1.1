<x-filament-panels::page>
    <div class="ecommerce-web-cork-screen web-cork-screen space-y-4">
        <h2 class="text-xl font-bold">Modül Yönetimi</h2>
        <p class="text-gray-600">Anasayfa modül section içeriklerini aşağıdaki bağlantılardan düzenleyebilirsiniz.</p>

        <div class="web-cork-card rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <h3 class="font-semibold mb-3">3. Grup Modüller</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                <a href="{{ \App\Filament\Clusters\Web\Pages\ModulNedenBiz::getUrl() }}" class="fi-link">Neden Biz</a>
                <a href="{{ \App\Filament\Clusters\Web\Pages\ModulNelerYapiyoruz::getUrl() }}" class="fi-link">Neler Yapıyoruz</a>
                <a href="{{ \App\Filament\Clusters\Web\Pages\ModulReferanslar::getUrl() }}" class="fi-link">Referanslar</a>
                <a href="{{ \App\Filament\Clusters\Web\Pages\ModulRakamlarlaBiz::getUrl() }}" class="fi-link">Rakamlarla Biz</a>
                <a href="{{ \App\Filament\Clusters\Web\Pages\ModulTeknikDestek::getUrl() }}" class="fi-link">Teknik Destek</a>
                <a href="{{ \App\Filament\Clusters\Web\Pages\ModulMusteriYorumlari::getUrl() }}" class="fi-link">Müşteri Yorumları</a>
                <a href="{{ \App\Filament\Clusters\Web\Pages\ModulFaqs::getUrl() }}" class="fi-link">SSS</a>
            </div>
        </div>
    </div>
</x-filament-panels::page>
