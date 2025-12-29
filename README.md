# Woo MNG Dev

Bu repo, **WooCommerce + MNG Kargo entegrasyonu** geliştirmek ve test etmek için hazırlanmış
bir **WordPress/WooCommerce geliştirme ortamı** ve ilgili **özel eklentiyi (`wc-mng-kargo`)**
içerir.

- WordPress + WooCommerce Docker ortamı  
- `wp-content/plugins/wc-mng-kargo` altında MNG Kargo shipping eklentisi  
- Canlı sitenizden bağımsız, güvenli bir geliştirme alanı

> Not: Bu repo **geliştirme amacıyla** hazırlanmıştır; prod ortam için ayarlarınızı ve
> credential’larınızı ayrı yönetmeniz önerilir.

---

## İçindekiler

- [Özellikler](#özellikler)
- [Teknolojiler](#teknolojiler)
- [Proje Yapısı](#proje-yapısı)
- [Gereksinimler](#gereksinimler)
- [Kurulum](#kurulum)
  - [1. Reponun klonlanması](#1-reponun-klonlanması)
  - [2. Docker ortamının ayağa kaldırılması](#2-docker-ortamının-ayağa-kaldırılması)
  - [3. WordPress kurulumu](#3-wordpress-kurulumu)
  - [4. Eklentinin aktifleştirilmesi](#4-eklentinin-aktifleştirilmesi)
- [Geliştirme](#geliştirme)
- [Yapılandırma Notları](#yapılandırma-notları)

---

## Özellikler

- MNG Kargo için özel WooCommerce shipping eklentisi iskeleti
- Yerel geliştirme için Docker Compose ile hızlı WordPress ortamı
- Eklenti kaynak kodu doğrudan `wp-content/plugins` altında
- Canlı WooCommerce sitenizi bozmadan entegrasyon denemeleri yapma imkânı

---

## Teknolojiler

- **PHP** (WordPress / WooCommerce eklenti geliştirme)
- **WordPress** + **WooCommerce**
- **Docker** & **Docker Compose**

---

## Proje Yapısı

Repo kök dizini:

```text
woo-mng-dev/
├─ docker-compose.yml          # WordPress/WooCommerce geliştirme ortamı için Docker tanımı
├─ .gitignore
└─ wp-content/
   └─ plugins/
      └─ wc-mng-kargo/        # MNG Kargo WooCommerce eklentisinin kaynak kodu
```

---

## Gereksinimler

- Git
- Docker
- Docker Compose

---

## Kurulum

### 1. Reponun klonlanması

```bash
git clone https://github.com/aydinmetee/woo-mng-dev.git
cd woo-mng-dev
```

### 2. Docker ortamının ayağa kaldırılması

```bash
docker-compose up -d
```

Bu komut:

- WordPress container’ını
- Gerekli veritabanı container’ını (örn. MySQL/MariaDB)

arka planda ayağa kaldırır.

> Hangi portların kullanıldığı, veritabanı adı, kullanıcı adı/şifre gibi detaylar için  
> **`docker-compose.yml`** dosyasına bakabilirsiniz.

### 3. WordPress kurulumu

1. Tarayıcınızdan `docker-compose.yml` içinde tanımlı WordPress host/port adresine gidin  
   (örn. `http://localhost:8080`).
2. WordPress ilk kurulum sihirbazını tamamlayın.

### 4. Eklentinin aktifleştirilmesi

1. WordPress admin paneline girin (`/wp-admin`).
2. **Eklentiler (Plugins)** menüsüne gidin.
3. Listede `wc-mng-kargo` / **MNG Kargo** eklentisini bulun.
4. **Etkinleştir (Activate)** butonuna tıklayın.

---

## Geliştirme

Eklenti kaynak kodu:

```text
wp-content/plugins/wc-mng-kargo/
```

- Kod değişikliklerini bu dizinde yapın.  
- Genelde tarayıcıyı yenilemek yeterlidir.  
- Gerekirse WordPress/WooCommerce debug modlarını açabilirsiniz.

```bash
docker-compose logs -f   # Logları izlemek için
```

---

## Yapılandırma Notları

- MNG Kargo API bilgileri (kullanıcı adı, şifre, şube kodu, servis tipi vb.)
  genellikle:
  - WooCommerce → **Ayarlar → Kargo** ekranında
  - veya eklentinin kendi ayar sayfasında
  yapılandırılır.
- Gerçek API bilgilerinizi repoya eklemeyin — ortam değişkenleri veya admin ayarlarını kullanın.

> Güvenlik: Prod API credential’larını asla Git’e commit etmeyin.


