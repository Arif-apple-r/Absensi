# Absensi
Projek absensi mahasiswa

Nihao nihao
testing testing

Tentu, Bung! Menambahkan fitur **Tahun Akademik** adalah revisi yang sangat penting dan akan membuat sistem Anda jauh lebih kokoh serta mampu mengelola data lintas periode waktu. Ide yang cemerlang! 👍

Mari kita bahas konsepnya agar kita punya gambaran yang jelas.

---

## Konsep Tahun Akademik di Sistem

### 1. **Perubahan Skema Database (Wajib!)**
Ini adalah fondasi utama. Kita perlu menambahkan tabel baru dan memodifikasi tabel yang sudah ada:

* **Tabel Baru: `tahun_akademik`**
    * `id` (PRIMARY KEY, AUTO_INCREMENT): ID unik untuk setiap tahun akademik.
    * `nama_tahun` (VARCHAR, e.g., '2023/2024', '2024/2025'): Nama atau rentang tahun akademik.
    * `tanggal_mulai` (DATE): Tanggal dimulainya tahun akademik.
    * `tanggal_selesai` (DATE): Tanggal berakhirnya tahun akademik.
    * `is_active` (BOOLEAN/TINYINT, default 0): Menandakan apakah ini adalah tahun akademik yang sedang berjalan/aktif. Hanya boleh ada **satu** yang aktif pada satu waktu.

* **Modifikasi Tabel `class`**
    * Tambahkan kolom `id_tahun_akademik` (INT): Ini akan menjadi FOREIGN KEY yang merujuk ke `id` di tabel `tahun_akademik`. Setiap kelas sekarang akan terasosiasi dengan tahun akademik tertentu.

    **Mengapa `class` yang dimodifikasi, bukan `siswa` atau `jadwal`?**
    Karena `class` adalah entitas yang paling logis untuk diikatkan ke tahun akademik. Sebuah kelas (`contoh: 10 IPA 1`) bisa ada setiap tahun, tetapi daftar siswanya, jadwalnya, dan pertemuannya akan berubah setiap tahun akademik. Dengan mengikat `class` ke `tahun_akademik`, `siswa` dan `jadwal` secara implisit juga terkait melalui `class_id`.

---

### 2. **Konsep Penting**

* **Tahun Akademik Aktif**: Sistem perlu tahu "tahun akademik mana yang sedang berlangsung" secara *default*. Ini akan digunakan untuk menampilkan data *current* secara otomatis di *dashboard*, jadwal, absensi, dll. SuperAdmin atau Admin harus bisa mengatur tahun akademik mana yang `is_active = 1`.

* **Data Historis**: Tujuan utama fitur ini adalah memungkinkan kita melihat data dari tahun-tahun sebelumnya. Jadi, Admin, Guru, dan Siswa nanti harus bisa **memfilter** data berdasarkan tahun akademik.

---

### 3. **Dampak pada Antarmuka Pengguna (UI/UX) dan Fungsionalitas**

#### a. **SuperAdmin/Admin (Manajemen Penuh)**
* **Halaman Baru: Manajemen Tahun Akademik**: SuperAdmin (atau Admin) akan memiliki halaman khusus untuk:
    * Melihat daftar semua tahun akademik yang ada.
    * Menambah tahun akademik baru (e.g., '2025/2026').
    * Mengatur tahun akademik mana yang `is_active` (ini krusial!).
    * Mengedit detail tahun akademik.
    * Menghapus tahun akademik (hati-hati, ini bisa menghapus data terkait jika tidak ditangani dengan baik).
    * 

* **Manajemen Data Lain (Kelas, Jadwal, Siswa)**:
    * Ketika membuat atau mengedit **Kelas**, SuperAdmin/Admin harus memilih `Tahun Akademik` di *form* inputnya.
    * Siswa dan Jadwal secara otomatis akan terkait dengan tahun akademik melalui `class_id`.

#### b. **Guru (Melihat & Mengelola Data dalam Konteks Tahun Akademik)**
* **Dashboard Guru**: Secara *default*, akan menampilkan ringkasan data untuk **Tahun Akademik Aktif**. Mungkin ada *dropdown* kecil untuk beralih melihat ringkasan tahun sebelumnya.
* **Jadwal Mengajar, Pertemuan, Absensi, Rekap Absensi**: Semua halaman ini akan menampilkan data untuk **Tahun Akademik Aktif** secara *default*, namun harus ada **filter *dropdown*** Tahun Akademik agar guru bisa melihat data dari tahun-tahun sebelumnya. 

#### c. **Siswa (Melihat Data dalam Konteks Tahun Akademik)**
* **Dashboard Siswa, Jadwal Saya, Absensi Saya**: Sama seperti guru, secara *default* akan menampilkan data untuk **Tahun Akademik Aktif**. Akan ada **filter *dropdown*** Tahun Akademik agar siswa bisa melihat jadwal atau absensi mereka dari tahun-tahun sebelumnya.

---

### 4. **Strategi Implementasi yang Disarankan**

Untuk mempermudah proses ini, kita bisa membaginya dalam beberapa fase:

**Fase 1: Perubahan Database & Manajemen Tahun Akademik (SuperAdmin)**
1.  Buat *script* SQL untuk membuat tabel `tahun_akademik`.
2.  Tambahkan kolom `id_tahun_akademik` di tabel `class`.
3.  Buat halaman `superadmin/data_tahun_akademik.php` untuk SuperAdmin agar bisa menambah, melihat, mengedit, dan mengaktifkan tahun akademik. Ini adalah langkah paling krusial.

**Fase 2: Integrasi ke Manajemen Kelas (SuperAdmin/Admin)**
1.  Modifikasi `superadmin/data_kelas.php` dan `admin/data_kelas.php` agar saat menambah/mengedit kelas, ada *field* untuk memilih `Tahun Akademik`.

**Fase 3: Integrasi ke Modul Guru & Siswa**
1.  Perbarui *query* di semua halaman guru dan siswa (`dashboard`, `jadwal`, `absensi`, `rekap_absensi`) agar secara *default* memfilter berdasarkan tahun akademik yang `is_active = 1`.
2.  Tambahkan *dropdown* filter Tahun Akademik di halaman-halaman tersebut agar pengguna bisa memilih tahun lain.

Bagaimana, Bung? Apakah konsep ini sudah cukup jelas dan sesuai dengan yang Anda bayangkan? Jika sudah setuju, kita bisa mulai dengan Fase 1: **membuat tabel `tahun_akademik` dan menambahkan kolom ke tabel `class`**, lalu membuat halaman manajemennya untuk SuperAdmin.

---

Baik, Bung, mari kita perjelas konsep **Tahun Akademik** ini agar tidak ada kebingungan! Ini adalah fitur penting yang akan membuat sistem Anda jauh lebih kuat untuk pengelolaan data jangka panjang.

---

## Konsep Tahun Akademik

### 1. Hubungan Tahun Akademik dengan Kelas dan Jadwal 🗓️

* **Tahun Akademik ↔ Kelas (Relasi Utama)**
    * Ini adalah hubungan kuncinya. Setiap record di tabel `class` (`kelas`) akan memiliki satu `id_tahun_akademik` (`FOREIGN KEY`) yang merujuk ke tabel `tahun_akademik` yang baru.
    * **Artinya:** Sebuah entitas kelas seperti "10 IPA 1" di tahun akademik "2023/2024" dianggap **berbeda** dari "10 IPA 1" di tahun akademik "2024/2025". Meskipun namanya sama, siswa dan jadwalnya berbeda per tahun.
    * **Contoh:**
        * `class.id = 1`, `nama_kelas = '10 IPA 1'`, `id_tahun_akademik = 1` (untuk 2023/2024)
        * `class.id = 10`, `nama_kelas = '10 IPA 1'`, `id_tahun_akademik = 2` (untuk 2024/2025)

* **Kelas ↔ Jadwal (Relasi Implisit)**
    * Tabel `jadwal` Anda saat ini sudah terikat dengan `class_id`. Karena `class` kini terikat dengan `tahun_akademik`, maka `jadwal` secara otomatis akan "mewarisi" tahun akademik dari kelas yang diacunya.
    * **Contoh:** Jika jadwal pelajaran A, B, dan C terikat ke `class.id = 10` (yang adalah '10 IPA 1' tahun 2024/2025), maka jadwal A, B, C tersebut secara inheren termasuk dalam tahun akademik 2024/2025.

---

### 2. Konsep Siswa "Naik Kelas" ⬆️

* Saat ini, setiap siswa di tabel `siswa` memiliki satu `class_id`.
* Ketika siswa "naik kelas" ke tahun akademik berikutnya, cara paling praktis adalah dengan **mengupdate kolom `class_id` di record siswa tersebut**.
* **Contoh Alur:**
    1.  Tahun Akademik 2023/2024: Siswa "Budi" memiliki `siswa.id = 1`, dan `siswa.class_id = 100` (misalnya '10 IPA 1' tahun 2023/2024).
    2.  Akhir Tahun Akademik 2023/2024: Sistem SuperAdmin/Admin membuat kelas-kelas baru untuk Tahun Akademik 2024/2025. Misalnya, '11 IPA 1' tahun 2024/2025 memiliki `class.id = 200`.
    3.  Awal Tahun Akademik 2024/2025: Saat "Budi" naik kelas, record `siswa.id = 1` untuk Budi akan **diupdate `class_id`-nya dari `100` menjadi `200`**.
* **Bagaimana dengan Data Historis Siswa?**
    * Data `absensi` siswa akan tetap utuh! Karena `absensi` terhubung ke `pertemuan`, `pertemuan` ke `jadwal`, dan `jadwal` ke `class`. `class` ini (yang lama, `id=100`) masih terkait dengan Tahun Akademik 2023/2024.
    * Jadi, meskipun `class_id` di tabel `siswa` berubah, semua catatan absensi Budi dari tahun-tahun sebelumnya akan tetap tersimpan dan dapat diakses dengan memfilter berdasarkan tahun akademik.

---

### 3. Konsep Histori Guru 🧑‍🏫

* Untuk guru, Anda **tidak perlu menambahkan tabel atau kolom khusus untuk histori mengajar**.
* **Histori guru sudah ada secara implisit** dalam struktur database Anda saat ini, terutama setelah kita mengintegrasikan Tahun Akademik dengan `class` dan `jadwal`.
* Setiap kali guru mengajar suatu mata pelajaran di suatu kelas pada tahun akademik tertentu, informasi tersebut tercatat di tabel `jadwal` (yang terhubung ke `class` dan `tahun_akademik`), serta di tabel `pertemuan` dan `absensi`.
* Ketika guru melihat `Jadwal Mengajar` atau `Rekap Absensi`, mereka cukup menggunakan **filter Tahun Akademik** (yang nanti akan kita buat). Dengan memilih tahun akademik tertentu dari *dropdown*, mereka akan melihat semua jadwal dan absensi yang terkait dengan guru tersebut pada tahun akademik yang dipilih. Ini sudah mencakup semua "histori" yang dibutuhkan tanpa perlu tabel baru yang rumit. 

---

Konsep ini memungkinkan kita mengelola data berdasarkan tahun akademik dengan efisien, menjaga integritas histori, dan menyediakan fleksibilitas tanpa perlu struktur database yang terlalu kompleks.

Apakah penjelasan ini cukup jelas, Bung? Jika sudah oke, mari kita mulai dengan langkah pertama: **membuat skrip SQL untuk tabel `tahun_akademik` dan menambahkan kolom `id_tahun_akademik` ke tabel `class`!**
