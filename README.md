# Absensi
Projek absensi mahasiswa

Nihao nihao
testing testing

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
