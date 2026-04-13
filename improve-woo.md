# SanalPosPRO WooCommerce Modülü İyileştirme Notları

Bu doküman, depo içindeki ödeme akışını güvenlik, stabilite, bakım maliyeti ve kod temizliği açısından iyileştirmek için hazırlanmıştır.

İnceleme kapsamı:
- PHP ana akışlar
- Internal API ve HTTP client
- Checkout ve Blocks JS entegrasyonu
- Uninstall ve yönetim paneli entegrasyonu

## Uygulama Prensibi (Breaking Degisiklik Yok)

Bu rapordaki tum iyilestirmeler, eklentinin mevcut calisan sozlesmesini bozmadan uygulanmalidir.

- Mevcut fonksiyon/method adlari korunmali.
- Mevcut action/filter hook adlari korunmali.
- Mevcut option key, nonce action ve request parametre adlari korunmali.
- Gerekli durumlarda yeniden adlandirma yerine alias/deprecation modeli kullanilmali.

Not:
- PHP'de degisken ve dizi keyleri buyuk-kucuk harfe duyarlidir.
- WordPress hook, option ve request keyleri string eslesmesi ile calistigi icin adlar birebir korunmalidir.

## 1. En Kritik Konular (P0)

1. Herkese açık AJAX endpoint üzerinden kritik ayarların değiştirilebilmesi
Sorun:
Frontend tarafında nonce dağıtılıyor ve nopriv AJAX endpoint açık. InternalApi dinamik action çalıştırdığı için, uygun role kontrolü olmadan kritik actionlar tetiklenebilir.

Neden riskli:
API key değiştirme, modül ayarı değiştirme gibi işlemler yetkisiz kullanıma açılabilir.

Öneri:
- Nopriv hook kaldırılmalı.
- Action bazlı yetki kontrolü eklenmeli.
- InternalApi içinde action allowlist ve capability eşlemesi yapılmalı.
- Admin actionları ayrı endpointte, checkout actionları ayrı endpointte tutulmalı.

Referanslar:
- [sanalpospro-payment-module/sanalpospro.php](sanalpospro-payment-module/sanalpospro.php#L47)
- [sanalpospro-payment-module/sanalpospro.php](sanalpospro-payment-module/sanalpospro.php#L615)
- [sanalpospro-payment-module/sanalpospro.php](sanalpospro-payment-module/sanalpospro.php#L738)
- [sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php](sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php#L79)
- [sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php](sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php#L99)
- [sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php](sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php#L133)
- [sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php](sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php#L562)

2. receipt_page içinde tanımsız değişken kullanımı
Sorun:
Order ID akışında tanımsız id değişkeni kullanılıyor.

Neden riskli:
PHP notice, yanlış order doğrulaması ve hatalı ödeme teyidi akışı üretebilir.

Öneri:
- id değişkeni tamamen kaldırılıp order_id net kullanılmalı.
- query paramdan gelen key ile sipariş eşlemesi zorunlu hale getirilmeli.

Referanslar:
- [sanalpospro-payment-module/sanalpospro.php](sanalpospro-payment-module/sanalpospro.php#L459)

3. Ödeme onayında idempotency eksikliği ve komisyonun tekrarlı eklenebilmesi
Sorun:
Başarılı callbackte her çağrıda fee ekleniyor. Zaten işlendi mi kontrolü yok.

Neden riskli:
Aynı ödeme callbackinin tekrar tetiklenmesi halinde sipariş tutarı bozulabilir.

Öneri:
- Order meta içine transaction id/process id kaydedilip yeniden çalıştırma engellenmeli.
- Fee eklemeden önce mevcut fee itemları kontrol edilmeli.
- amount ve merchant_reference ile order tutarlılığı doğrulanmalı.

Referanslar:
- [sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php](sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php#L516)
- [sanalpospro-payment-module/sanalpospro.php](sanalpospro-payment-module/sanalpospro.php#L504)
- [sanalpospro-payment-module/sanalpospro.php](sanalpospro-payment-module/sanalpospro.php#L509)
- [sanalpospro-payment-module/sanalpospro.php](sanalpospro-payment-module/sanalpospro.php#L510)

4. ApiClient ve ApiResponse hata modeli tutarsız
Sorun:
ApiResponse içinde response array iken object gibi okunuyor. ApiClient bazen string, bazen array dönüyor.

Neden riskli:
Hata durumunda upstream kod array beklerken string alıp kırılabilir. Hata mesajları kaybolabilir.

Öneri:
- Tüm API yanıtları tek formatta dönmeli: status, message, data, details.
- getError/getMessage array erişimi ile düzeltilmeli.
- curl/json parse hatalarında da array formatı dönmeli.

Referanslar:
- [sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/ApiResponse.php](sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/ApiResponse.php#L50)
- [sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/ApiResponse.php](sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/ApiResponse.php#L55)
- [sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/ApiClient.php](sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/ApiClient.php#L83)

5. API header birikmesi ve istek başına resetlenmemesi
Sorun:
Headers array her çağrıda append ediliyor, temizlenmiyor.

Neden riskli:
Uzun süreli request zincirinde duplicate header ve beklenmedik davranış üretir.

Öneri:
- call başında headers boşaltılmalı.
- setHeaders saf fonksiyon yaklaşımıyla tek seferde header listesi döndürmeli.

Referanslar:
- [sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/ApiClient.php](sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/ApiClient.php#L43)
- [sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/ApiClient.php](sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/ApiClient.php#L47)
- [sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/ApiClient.php](sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/ApiClient.php#L69)

6. Payment link üretiminde cart bağımlılığı
Sorun:
CreatePaymentLink sadece WC cart üzerinden ilerliyor.

Neden riskli:
Pay for order veya sepet temizlendikten sonra ödeme denemelerinde başarısızlık üretir.

Öneri:
- Ödeme requesti order itemlarından inşa edilmeli.
- Cart yoksa order fallback yolu zorunlu olmalı.

Referanslar:
- [sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php](sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php#L272)
- [sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php](sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php#L290)
- [sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php](sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php#L294)

7. Gerçek olmayan fallback veri kullanımı
Sorun:
Adres, şehir, state, telefon gibi alanlar sabit test değerlerine düşüyor.

Neden riskli:
Fraud/antifraud ve PSP doğrulama süreçlerinde red oranını artırır.

Öneri:
- Zorunlu alanları checkout aşamasında doğrula, yoksa anlaşılır hata dön.
- IP için wc_get_user_ip_address benzeri güvenli fallback kullan.
- Test verisi yerine validation error yaklaşımına geç.

Referanslar:
- [sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php](sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php#L442)
- [sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php](sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php#L449)
- [sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php](sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php#L456)
- [sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php](sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php#L473)
- [sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php](sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php#L474)
- [sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php](sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php#L482)

## 2. Yüksek Öncelik (P1)

1. Uninstall option key tutarsızlığı
Sorun:
Kurulum ve runtime SANALPOSPRO prefiksi kullanıyor, uninstall SPPRO prefiksi siliyor.

Etkisi:
Veritabanında stale option kalır.

Uyumlu cozum:
- Runtime'da kullanilan mevcut key adlari degistirilmemeli.
- Uninstall tarafinda hem SANALPOSPRO hem SPPRO prefiksli keyler temizlenmeli.
- Bu sayede davranis degismeden sadece temizlik duzeltilmis olur.

Referanslar:
- [sanalpospro-payment-module/sanalpospro.php](sanalpospro-payment-module/sanalpospro.php#L221)
- [sanalpospro-payment-module/sanalpospro.php](sanalpospro-payment-module/sanalpospro.php#L222)
- [sanalpospro-payment-module/sanalpospro.php](sanalpospro-payment-module/sanalpospro.php#L223)
- [sanalpospro-payment-module/sanalpospro.php](sanalpospro-payment-module/sanalpospro.php#L224)
- [sanalpospro-payment-module/sanalpospro.php](sanalpospro-payment-module/sanalpospro.php#L225)
- [sanalpospro-payment-module/uninstall.php](sanalpospro-payment-module/uninstall.php#L20)
- [sanalpospro-payment-module/uninstall.php](sanalpospro-payment-module/uninstall.php#L21)
- [sanalpospro-payment-module/uninstall.php](sanalpospro-payment-module/uninstall.php#L22)
- [sanalpospro-payment-module/uninstall.php](sanalpospro-payment-module/uninstall.php#L23)
- [sanalpospro-payment-module/uninstall.php](sanalpospro-payment-module/uninstall.php#L24)

2. Checkout uyumluluk kontrol fonksiyonu dead code
Sorun:
Fonksiyon tanımlı ama hooklanmamış.

Etkisi:
Admin uyarısı beklenen durumda görünmeyebilir.

Referanslar:
- [sanalpospro-payment-module/sanalpospro.php](sanalpospro-payment-module/sanalpospro.php#L83)
- [sanalpospro-payment-module/sanalpospro.php](sanalpospro-payment-module/sanalpospro.php#L42)
- [sanalpospro-payment-module/sanalpospro.php](sanalpospro-payment-module/sanalpospro.php#L43)

3. Redirect güvenliği
Sorun:
wp_redirect kullanılıyor.

Etkisi:
Gelecekte dış URL eklenirse open redirect riski oluşabilir.

Öneri:
wp_safe_redirect tercih edin.

Referanslar:
- [sanalpospro-payment-module/sanalpospro.php](sanalpospro-payment-module/sanalpospro.php#L487)
- [sanalpospro-payment-module/sanalpospro.php](sanalpospro-payment-module/sanalpospro.php#L514)
- [sanalpospro-payment-module/sanalpospro.php](sanalpospro-payment-module/sanalpospro.php#L518)

4. Iframe event origin sabit değer
Sorun:
PostMessage origin kontrolü sabit domaine bağlı.

Etkisi:
Provider domain değişirse callback yakalanamaz.

Öneri:
Origin, payment_link üzerinden parse edilip allowlist ile doğrulanmalı.

Referanslar:
- [sanalpospro-payment-module/assets/js/checkout.js](sanalpospro-payment-module/assets/js/checkout.js#L24)
- [sanalpospro-payment-module/assets/js/blocks-integration.js](sanalpospro-payment-module/assets/js/blocks-integration.js#L184)

5. Inline handler kullanımı
Sorun:
Template içinde onclick ve onload inline tutuluyor.

Etkisi:
CSP sertleştirme ve test edilebilirlik zorlaşır.

Öneri:
Event binding JS dosyasına taşınmalı.

Referanslar:
- [sanalpospro-payment-module/templates/checkout/payment-iframe.php](sanalpospro-payment-module/templates/checkout/payment-iframe.php#L19)
- [sanalpospro-payment-module/templates/checkout/payment-iframe.php](sanalpospro-payment-module/templates/checkout/payment-iframe.php#L35)

## 3. Kod Temizliği ve Bakım (P2)

1. JSON alanlarının sanitize_text_field ile alınması
Sorun:
JSON payloadlar text sanitize edilip sonra decode ediliyor.

Etkisi:
Özellikle özel karakter içeren payloadlarda parse/doğruluk sorunları oluşabilir.

Öneri:
wp_unslash sonrası strict JSON decode ve schema doğrulaması uygulayın.

Referanslar:
- [sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/EticTools.php](sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/EticTools.php#L12)
- [sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/EticTools.php](sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/EticTools.php#L19)
- [sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php](sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php#L89)

2. İsimlendirme ve API tutarlılığı
Sorun:
getInstanse yazım hatası, farklı sınıflarda dönüş sözleşmeleri heterojen.

Etkisi:
Okunabilirlik ve onboarding zorlaşır.

Uyumlu cozum:
- Mevcut getInstanse adi dogrudan degistirilmemeli.
- Yeni bir getInstance alias method eklenip, mevcut cagrilar calismaya devam etmeli.
- Kod ici gecis kademeli yapilmali; tek seferde rename yapilmamali.

Referanslar:
- [sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/ApiClient.php](sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/ApiClient.php#L104)

3. Admin script enqueue sadeleştirme
Sorun:
Aynı handle register sonrası enqueue çağrısı karmaşık ve okunması zor.

Öneri:
Tek bir register plus enqueue akışı ve filtre yönetimi kullanın.

Referanslar:
- [sanalpospro-payment-module/admin/class-admin.php](sanalpospro-payment-module/admin/class-admin.php#L96)
- [sanalpospro-payment-module/admin/class-admin.php](sanalpospro-payment-module/admin/class-admin.php#L105)
- [sanalpospro-payment-module/admin/class-admin.php](sanalpospro-payment-module/admin/class-admin.php#L112)

4. Composer/autoload eksikliği
Sorun:
Vendor include manuel require zinciriyle yönetiliyor.

Etkisi:
Bağımlılık yönetimi, test ve sürümleme süreçleri zorlaşır.

Referanslar:
- [sanalpospro-payment-module/vendor/include.php](sanalpospro-payment-module/vendor/include.php)

## 4. Fonksiyon Bazlı Stabilizasyon Önerileri

Bu bolumdeki tum adimlar, mevcut fonksiyon ve parametre isimleri korunarak uygulanmalidir.

1. process_payment
Öneri:
- InternalApi dönüş modelini type-safe hale getir.
- payment_link ve redirect_url yoksa fail fast uygula.
- Exception mesajlarını kullanıcıya ham vermek yerine güvenli metin göster, detayları logla.

Referans:
- [sanalpospro-payment-module/sanalpospro.php](sanalpospro-payment-module/sanalpospro.php#L366)

2. receipt_page
Öneri:
- order_id, key ve nonce üçlüsünü birlikte doğrula.
- Aynı process_token ikinci kez işlenirse no-op yap.
- Komisyon fee ekleme öncesinde mevcut fee itemlarını kontrol et.

Referans:
- [sanalpospro-payment-module/sanalpospro.php](sanalpospro-payment-module/sanalpospro.php#L434)

3. InternalApi actionCreatePaymentLink
Öneri:
- Order merkezli request oluştur.
- Customer datada zorunlu alan kontrolü ve anlaşılır hata kodları üret.
- Random id üretiminde random_int veya deterministic key kullan.

Referans:
- [sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php](sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/InternalApi.php#L272)

4. ApiClient call
Öneri:
- Timeout, connect timeout, ssl verify, user-agent ve retry stratejisi ekle.
- Header listesi ve hata sözleşmesini normalize et.

Referans:
- [sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/ApiClient.php](sanalpospro-payment-module/vendor/Eticsoft/Sanalpospro/ApiClient.php#L69)

## 5. Hızlı Uygulanabilir Yol Haritası

İlk sprint:
- Nopriv endpoint kapatma, capability kontrolü, action allowlist.
- receipt_page id ve idempotency düzeltmesi.
- ApiResponse ve ApiClient dönüş sözleşmesi standardizasyonu.

İkinci sprint:
- Cart bağımlılığını order tabanına taşıma.
- Uninstall option key düzeltmesi.
- Inline JS handler temizliği.

Üçüncü sprint:
- Composer autoload, phpcs ve phpstan ekleme.
- Temel entegrasyon testleri.

## 6. Test Stratejisi Notu

Depoda test dosyası görünmüyor. En azından aşağıdaki smoke test seti önerilir:
- Başarılı ödeme link oluşturma
- Invalid nonce ile receipt akışı reddi
- Aynı callback tekrarlandığında duplicate fee oluşmaması
- API timeout ve invalid JSON senaryoları
- Uninstall sonrası option temizliği doğrulaması
