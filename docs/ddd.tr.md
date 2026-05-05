# DDD

Bu döküman, starter kit'in kullandığı domain odaklı yapıyı açıklar. Controller merkezli değil domain merkezli bir DDD düzeni uygulanır.

## Amaç

Temel amaç, iş kurallarını büyüyen controller dosyalarından çıkarıp öngörülebilir domain klasörlerine taşımaktır.

## Domain Yapısı

Kurulumdan sonra tipik yapı şöyledir:

```text
app/Domain/
├── ActivityLog/
├── ApiRoute/
├── Auth/
├── FileManager/
├── Media/
├── Role/
├── Session/
├── Setting/
├── Shared/
└── User/
```

Bir domain içinde genelde şu katmanlar bulunur:

- yazma işlemleri ve use-case akışı için `Actions`
- doğrulanmış veriyi taşımak için `DTOs`
- listeleme ve datatable sorgu mantığı için `Queries`
- domain olayları için `Events`
- loglama gibi yan etkiler için `Listeners`
- soyutlama gerektiğinde `Repositories` veya `Contracts`

## Request Akışı

Tipik akış:

1. Controller isteği alır.
2. Form Request doğrulama yapar.
3. DTO veriyi normalize eder.
4. Action iş kuralını çalıştırır.
5. Gerekirse Event fırlatılır.
6. Listener controller'ı şişirmeden yan etkileri işler.
7. Yanıt `to_api()` ya da Inertia redirect ile döner.

## Temel Kurallar

- controller'ları ince tutun
- doğrulamayı Form Request içinde tutun
- karmaşık yazma işlemlerini Action içine alın
- tekrar kullanılan liste mantığını Query içinde tutun
- yan etkileri Listener katmanına taşıyın
- domainler arası ortak kodu `app/Domain/Shared` altında toplayın

## Neden Faydalı

- büyük admin projelerinde okunabilirliği artırır
- iş kurallarını test etmeyi kolaylaştırır
- zaman içinde refactor sürecini güvenli hale getirir
- web ve API controller'ları arasında tekrarları azaltır

## İlgili Komutlar

Domain yapısı scaffolding komutlarıyla desteklenir, ancak komut referansı [artisan-commands.tr.md](./artisan-commands.tr.md) içinde tutulur. Bu dosya özellikle DDD anlatımını komut dökümanından ayrı tutmak için vardır.

