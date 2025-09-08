# Carta Alir (Flowchart) untuk Laman Web

Graf alir ringkas bagi sistem pengundian di laman web ini.

```mermaid
flowchart TD
    Start([Mula])
    A[Landing: `index.html`]
    A --> |"Admin"| B[Admin Login (admin.html)]
    B --> B1[Pengurusan Calon / Lihat Keputusan]
    B1 --> End

    A --> |"Pengundi"| C[Masuk kepada `index.php` (Borong/Undi)]
    # Carta Alir (Flowchart) untuk Laman Web

    Graf alir ringkas bagi sistem pengundian di laman web ini.

    ```mermaid
    flowchart TD
        Start([Mula])
        End([Selesai])
        A[Landing: index.html]
        A --> |Admin| B[Admin Login (admin.html)]
        B --> B1[Pengurusan Calon / Lihat Keputusan]
        B1 --> End

        A --> |Pengundi| C[Masuk kepada index.php (Borong/Undi)]
        Role{Adakah anda pentadbir?}
        NewUser{Adakah anda pengguna baru?}
        Start --> A

        A --> Role
        Role -->|Ya| B[Admin Login (admin.html)]
        B --> B1[Pengurusan Calon / Lihat Keputusan]
        B1 --> End

        Role -->|Tidak| AskNew[Teruskan sebagai Pengundi]
        AskNew --> NewUser
        NewUser -->|Ya| E[Daftar (borang pendaftaran)]
        # Carta Alir (Flowchart) untuk Laman Web

        Graf alir terperinci bagi sistem pengundian di laman web ini.

        ```mermaid
        flowchart TD
            Start([Mula])
            Menu[Papar Menu Utama]
            Start --> Menu

            Menu --> Role{Adakah anda pentadbir?}

            %% Admin flow
            Role -->|Ya| AdminLogin[Admin: Masukkan ID Pengguna\nKata Laluan]
            AdminLogin --> AdminAuth{Sahkan kelayakan}
            AdminAuth -->|Ya| AdminDash[Paparan Papan Pemuka Admin - Urus Calon dan Lihat Keputusan]
            AdminAuth -->|Tidak| AdminFail[Paparkan ralat dan kembali ke Login]
            AdminFail --> AdminLogin
            AdminDash --> End([Selesai])

            %% Pengundi flow
            Role -->|Tidak| NewUser{Pengguna baharu?}
            NewUser -->|Ya| Register[Daftar: ID Pengguna, Kata Laluan]
            Register --> RegCheck{Maklumat lengkap?}
            RegCheck -->|Ya| RegVerify[Pengesahan ID dan Aktifkan Akaun]
            RegCheck -->|Tidak| RegFail[Paparkan ralat dan kembali ke borang daftar]
            RegVerify --> UserLogin[Login: ID dan Kata Laluan]

            NewUser -->|Tidak| UserLogin

            UserLogin --> LoginAuth{Sah dan Akaun Aktif?}
            LoginAuth -->|Tidak| LoginFail[Paparkan ralat dan kembali ke Login]
            LoginAuth -->|Ya| CheckVoted{Telah mengundi?}
            CheckVoted -->|Ya| Already[Paparkan: Anda telah mengundi] --> End
            CheckVoted -->|Tidak| ShowCandidates[Paparkan Calon dan Borang Undi]
            ShowCandidates --> SubmitVote[Hantar Undi]
            SubmitVote -->|Berjaya| VoteConfirm[Terima Pengesahan Undi] --> End
            SubmitVote -->|Gagal| VoteFail[Paparkan ralat dan cuba semula]

            %% Kesalahan dan semakan
            AdminAuth -->|Kesilapan teknikal| AdminFail
            LoginAuth -->|Kesilapan teknikal| LoginFail

            style Start fill:#f9f,stroke:#333,stroke-width:2px
            style End fill:#bbf,stroke:#333,stroke-width:2px
        ```

        Nota ringkas:
        - Anggapan: `index.html` adalah halaman hadapan, `index.php` mengendalikan alur undi bagi pengundi, `admin.html` adalah papan pemuka pentadbir.

        Kontrak ringkas:
        - Input: pengguna melayari laman web.
        - Output: perjalanan pengguna (Admin vs Pengundi) sehingga pengundian selesai.
        - Kegagalan biasa: log masuk gagal, penghantaran undi gagal, pengundi sudah mengundi.

        Edge cases:
        - Pengguna cuba undi tanpa pendaftaran.
        - Pengguna kehilangan sambungan semasa penghantaran undi.
        - Serangan percubaan mengundi berganda (perlu hadkan di pelayan).

        Langkah seterusnya:
        - Buka `carta-alir.html` untuk melihat versi HTML yang dipaparkan terus di pelayar, atau lihat `carta-alir.md` dalam Markdown preview.
        - Beritahu saya jika anda mahu perubahan pada label nod (contoh: tukar "ID Pengguna" ke "Nombor Kad Pengenalan").
