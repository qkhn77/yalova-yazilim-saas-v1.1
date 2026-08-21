<x-filament-panels::page>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <div class="bg-white p-6 rounded-xl shadow">
            <h2 class="text-lg font-bold mb-4">
                Yeni Cihaz Kaydı
            </h2>

            <form class="space-y-4">

                <div>
                    <label class="text-sm font-medium">Müşteri Adı</label>
                    <input type="text"
                           class="w-full border rounded-lg px-3 py-2 mt-1"
                           placeholder="Müşteri adı girin">
                </div>

                <div>
                    <label class="text-sm font-medium">Telefon</label>
                    <input type="text"
                           class="w-full border rounded-lg px-3 py-2 mt-1"
                           placeholder="Telefon numarası">
                </div>

                <div>
                    <label class="text-sm font-medium">Cihaz Türü</label>
                    <select class="w-full border rounded-lg px-3 py-2 mt-1">
                        <option>Telefon</option>
                        <option>Bilgisayar</option>
                        <option>Tablet</option>
                        <option>Kamera</option>
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium">Marka</label>
                    <input type="text"
                           class="w-full border rounded-lg px-3 py-2 mt-1"
                           placeholder="Marka">
                </div>

                <div>
                    <label class="text-sm font-medium">Model</label>
                    <input type="text"
                           class="w-full border rounded-lg px-3 py-2 mt-1"
                           placeholder="Model">
                </div>

                <div>
                    <label class="text-sm font-medium">Arıza Açıklaması</label>
                    <textarea
                        class="w-full border rounded-lg px-3 py-2 mt-1"
                        rows="4"
                        placeholder="Cihaz arızasını yazın"></textarea>
                </div>

                <div class="pt-2">
                    <button
                        type="submit"
                        class="bg-amber-500 hover:bg-amber-600 text-white px-5 py-2 rounded-lg">
                        Cihaz Kaydet
                    </button>
                </div>

            </form>

        </div>

        <div class="bg-white p-6 rounded-xl shadow">
            <h2 class="text-lg font-bold mb-4">
                Bilgilendirme
            </h2>

            <ul class="text-sm text-gray-600 space-y-2">
                <li>• Yeni cihaz kayıtları burada oluşturulur</li>
                <li>• Müşteri bilgileri servis sürecinde kullanılır</li>
                <li>• Arıza açıklaması servis teknisyenine gönderilir</li>
            </ul>

        </div>

    </div>

</x-filament-panels::page>
