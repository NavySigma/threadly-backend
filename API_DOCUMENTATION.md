# Threadly API Documentation

**Base URL:** `http://localhost:8000/api`

**Auth:** Sanctum token via `Authorization: Bearer {token}` (didapat dari login)

---

## Daftar Isi

- [A. Auth](#a-auth)
- [B. User / Profile](#b-user--profile)
- [C. Posts](#c-posts)
- [D. Comments](#d-comments)
- [E. Follow](#e-follow)
- [F. Votes](#f-votes)
- [G. Likes / Bookmark](#g-likes--bookmark)
- [H. Bookmarks (Post)](#h-bookmarks-post)
- [I. Notifications](#i-notifications)
- [J. Reports](#j-reports)
- [K. Search](#k-search)
- [L. Categories](#l-categories)
- [M. Tags](#m-tags)
- [N. Points](#n-points)
- [O. Error Codes](#o-error-codes)
- [P. Point System](#p-point-system)
- [Q. Role & Permission](#q-role--permission)

---

## A. AUTH

### POST /api/register

Buat akun baru.

**Body:**

```json
{
    "username": "john",
    "email": "john@example.com",
    "password": "secret123",
    "password_confirmation": "secret123"
}
```

**Response** `201`:

```json
{
  "message": "Registrasi berhasil!",
  "data": { "user object with roles" }
}
```

**Error:**
| Kondisi | Status |
|---------|--------|
| Username/email sudah dipakai | `422` |
| Password < 3 karakter | `422` |
| Password confirmation tidak cocok | `422` |

> User baru otomatis dapat role `user` dan `reputation_points = 0`.

---

### POST /api/login

Login menggunakan **username** (bukan email).

**Body:**

```json
{
    "username": "john",
    "password": "secret123"
}
```

**Response** `200`:

```json
{
  "message": "Login berhasil!",
  "access_token": "1|xxx...",
  "token_type": "Bearer",
  "user": { "user object with roles" }
}
```

**Error:** `401` — `"Username atau password yang kamu masukkan salah."`

---

### POST /api/logout 🔐

Hapus token yang dipakai.

**Response** `200`:

```json
{
    "success": true,
    "message": "Berhasil logout. Token telah dihapus."
}
```

---

## B. USER / PROFILE

### GET /api/me 🔐

Profil sendiri (lengkap dengan email & roles).

**Response** `200`:

```json
{
    "data": {
        "id": "uuid",
        "username": "superadmin",
        "email": "admin@forum.com",
        "avatar_url": null,
        "bio": "...",
        "reputation_points": 9999,
        "level": 1,
        "created_at": "...",
        "updated_at": "...",
        "roles": [{ "id": "uuid", "name": "admin" }],
        "level_title": "Newbie",
        "next_level_points": 15
    }
}
```

---

### PUT /api/me 🔐

Edit profil sendiri.

**Body (semua `sometimes`):**

```json
{
    "username": "nama_baru",
    "avatar_url": "https://example.com/avatar.png",
    "bio": "bio baru"
}
```

**Response** `200`:

```json
{ "message": "Profile berhasil diupdate.", "data": { "updated user" } }
```

---

### PUT /api/me/password 🔐

Ganti password. **Semua token dihapus** — harus login ulang.

**Body:**

```json
{
    "current_password": "lama",
    "new_password": "baru12345",
    "new_password_confirmation": "baru12345"
}
```

**Response** `200`:

```json
{ "message": "Password berhasil diubah, silakan login ulang." }
```

---

### GET /api/users/{user} (Publik)

Lihat profil user lain.

**Response** `200`:

```json
{
    "data": {
        "id": "uuid",
        "username": "superadmin",
        "avatar_url": null,
        "bio": "...",
        "reputation_points": 9999,
        "created_at": "...",
        "followers_count": 0,
        "following_count": 0,
        "posts_count": 1
    }
}
```

---

### GET /api/users/{user}/posts (Publik)

Postingan milik user tertentu (hanya `status = open`).

**Query:** `?page=1`

**Response** `200` — paginated posts

---

## C. POSTS

### GET /api/posts (Publik)

Daftar post dengan filter.

**Query (semua optional):**
| Param | Deskripsi |
|-------|-----------|
| `search` | Cari di title/body |
| `category_id` | Filter kategori |
| `category_slug` | Filter kategori via slug |
| `tag_id` | Filter tag |
| `tag_slug` | Filter tag via slug |
| `user_id` | Filter penulis |
| `is_answered` | `true` / `false` |
| `sort` | `latest` (default), `oldest`, `popular`, `votes` |
| `page` | Pagination |

**Response** `200` — paginated with `user`, `category`, `tags`

---

### GET /api/posts/{post} (Publik)

Detail post. **View count increment otomatis.**

**Response** `200`:

```json
{
    "data": {
        "id": "uuid",
        "title": "...",
        "body": "...",
        "status": "open",
        "view_count": 3,
        "vote_score": 1,
        "is_answered": false,
        "accepted_answer_id": null,
        "user": { "id": "uuid", "username": "..." },
        "category": { "id": "uuid", "name": "...", "slug": "..." },
        "tags": [
            {
                "id": "uuid",
                "name": "Laravel",
                "slug": "laravel",
                "color": "#FF2D20"
            }
        ],
        "accepted_answer": null
    }
}
```

---

### POST /api/posts 🔐

Buat post baru.

> ⚠️ **Minimal 15 reputation points.**

**Body:**

```json
{
    "category_id": "uuid",
    "title": "Cara belajar Laravel untuk pemula",
    "body": "Halo semua, saya ingin bertanya tentang...",
    "tags": ["uuid_tag1", "uuid_tag2"]
}
```

> `tags` optional, maksimal 5 tag.

**Response** `201`:

```json
{ "message": "Post berhasil dibuat.", "data": { "post with user, category, tags" } }
```

**Error:** `422` — `"Minimal 15 poin untuk membuat postingan."`

---

### PUT /api/posts/{post} 🔐 (Owner atau Admin)

Edit post.

> ❌ Post `closed` tidak bisa diedit.
> ❌ Maksimal 2x edit per user per post.

**Body (semua `sometimes`):**

```json
{
    "category_id": "uuid",
    "title": "...",
    "body": "...",
    "tags": ["uuid"],
    "reason": "Alasan edit"
}
```

**Response** `200`:

```json
{ "message": "Post berhasil diupdate.", "data": { "updated post" } }
```

---

### DELETE /api/posts/{post} 🔐 (Owner, Mod, atau Admin)

Soft delete — status jadi `deleted`.

**Response** `200`:

```json
{ "message": "Post berhasil dihapus." }
```

---

### GET /api/posts/{post}/history 🔐 (Admin only)

Riwayat edit post.

**Response** `200`:

```json
{
    "data": [
        {
            "edited_by": "uuid",
            "body_before": "...",
            "body_after": "...",
            "edited_at": "..."
        }
    ]
}
```

---

## D. COMMENTS

### GET /api/posts/{post}/comments (Publik)

Daftar komentar top-level + replies (nested 1 level).

**Response** `200` — paginated

---

### POST /api/posts/{post}/comments 🔐

Buat komentar atau reply.

**Body (komentar baru):**

```json
{ "body": "Menurut saya..." }
```

**Body (reply):**

```json
{ "body": "Setuju!", "parent_id": "uuid_comment" }
```

**Rules:**
| Kondisi | Status |
|---------|--------|
| ✅ Komentar baru | `201` |
| ❌ Reply dari reply (depth > 1) | `422` |
| ❌ Post `deleted` / `closed` | `422` |
| ❌ Owner post maksimal 2 top-level comment | `422` |

---

### PUT /api/comments/{comment} 🔐 (Owner only)

Edit komentar. Maksimal 2x edit.

**Body:**

```json
{ "body": "Revisi komentar..." }
```

**Response** `200`

---

### DELETE /api/comments/{comment} 🔐 (Mod/Admin only)

Hapus komentar.

> Punya replies → soft delete (body jadi `[komentar telah dihapus]`)
> Tidak punya replies → hard delete

**Response** `200`

---

### POST /api/posts/{post}/comments/{comment}/accept 🔐 (Owner post)

Accept jawaban. Penulis komentar dapat **+10 points**.

> ❌ Tidak bisa accept reply.
> ❌ Comment harus dari post yang sama.

**Response** `200`:

```json
{ "message": "Jawaban berhasil diterima." }
```

---

### DELETE /api/posts/{post}/unaccept 🔐 (Owner post)

Batalkan accept answer.

**Response** `200`:

```json
{ "message": "Accepted answer berhasil dibatalkan." }
```

---

### GET /api/comments/{comment}/history 🔐 (Admin only)

Riwayat edit komentar.

**Response** `200`:

```json
{
    "data": [
        {
            "edited_by": "uuid",
            "body_before": "...",
            "body_after": "...",
            "edited_at": "..."
        }
    ]
}
```

---

## E. FOLLOW

### POST /api/users/{user}/follow 🔐

Follow user lain.

> ❌ Tidak bisa follow diri sendiri → `422`
> ❌ Sudah follow → `422`

**Response** `201`:

```json
{ "message": "Berhasil mengikuti." }
```

---

### DELETE /api/users/{user}/follow 🔐

Unfollow user. **Selalu sukses** meskipun belum follow (no-op).

**Response** `200`:

```json
{ "message": "Berhasil unfollow." }
```

---

### GET /api/users/{user}/followers (Publik)

Daftar followers.

**Response** `200`:

```json
{
    "data": [
        {
            "id": "uuid",
            "username": "...",
            "avatar_url": null,
            "reputation_points": 0
        }
    ],
    "meta": { "followers_count": 1 }
}
```

---

### GET /api/users/{user}/following (Publik)

Daftar yang di-follow.

**Response** `200` — struktur sama, dengan `meta.following_count`

---

## F. VOTES

### POST /api/votes 🔐

Upvote/downvote post atau comment.

**Body:**

```json
{
    "target_type": "post",
    "target_id": "uuid",
    "vote_type": "upvote"
}
```

**Rules:**
| Kondisi | Status | Message |
|---------|--------|---------|
| ✅ Vote baru | `201` | `"Vote berhasil."` |
| ❌ Vote konten sendiri | `422` | `"Tidak bisa vote konten sendiri."` |
| ❌ Downvote < 15 pts | `422` | `"Minimal 15 poin untuk downvote."` |
| ❌ Vote reply | `422` | `"Reply tidak bisa di-vote."` |
| ❌ Post ditutup | `422` | `"Post sudah ditutup."` |
| 🔄 Toggle off (sama) | `200` | `"Vote dibatalkan."` |
| 🔄 Ganti vote type | `200` | `"Vote berhasil diubah."` |

**Point impact:**
| Aksi | Voter | Owner |
|------|-------|-------|
| Upvote | 0 | +2 |
| Downvote | -1 | -2 |
| Batal upvote | 0 | -2 |
| Batal downvote | +1 | +2 |

---

## G. LIKES / BOOKMARK

### POST /api/likes 🔐

Like (bookmark) post atau comment. Mengirim notifikasi `like` ke pemilik konten.

**Body:**

```json
{
    "target_type": "post",
    "target_id": "uuid"
}
```

**Response** `201`:

```json
{ "message": "Berhasil di-bookmark." }
```

**Error:** `422` — `"Sudah di-bookmark."`

---

### DELETE /api/likes 🔐

Hapus like/bookmark.

**Body:** Sama seperti like.

**Response** `200`:

```json
{ "message": "Bookmark dihapus." }
```

---

### GET /api/me/bookmarks/posts 🔐

Daftar post yang di-like.

**Response** `200` — paginated, include target post dengan user, category, tags

---

### GET /api/me/bookmarks/comments 🔐

Daftar comment yang di-like.

**Response** `200` — paginated, include target comment + user + judul post

---

## H. BOOKMARKS (POST)

### GET /api/me/bookmarks 🔐

Daftar post yang di-bookmark (via BookmarkController).

**Response** `200` — paginated, include post dengan user, category, tags

---

### POST /api/bookmarks 🔐

Bookmark post.

**Body:**

```json
{ "post_id": "uuid" }
```

**Response** `201`:

```json
{ "message": "Post berhasil di-bookmark." }
```

---

### DELETE /api/bookmarks/{post} 🔐

Hapus bookmark post.

**Response** `200`:

```json
{ "message": "Bookmark berhasil dihapus." }
```

---

### GET /api/bookmarks/{post}/check 🔐

Cek apakah post sudah di-bookmark.

**Response** `200`:

```json
{ "is_bookmarked": true }
```

---

## I. NOTIFICATIONS

### GET /api/notifications 🔐

Daftar notifikasi.

**Query:** `?unread_only=1`

**Response** `200`:

```json
{
    "unread_count": 5,
    "data": {
        "data": [
            {
                "id": "uuid",
                "type": "follow",
                "is_read": false,
                "message": "...",
                "actor": {
                    "id": "uuid",
                    "username": "...",
                    "avatar_url": null
                },
                "created_at": "..."
            }
        ],
        "meta": { "current_page": 1, "last_page": 1, "total": 1 }
    }
}
```

**Notification types:**
| Type | Trigger |
|------|---------|
| `complete_profile` | Registrasi |
| `level_up` | Naik level |
| `comment` | Dikomentari |
| `reply_on_post` | Dibalas di post |
| `reply` | Dibalas komentar |
| `follow` | Di-follow |
| `upvote` | Di-upvote |
| `like` | Di-like |
| `answer_accepted` | Jawaban diterima |
| `report_confirmed` | Laporan dikonfirmasi |
| `report_penalized` | Konten dihapus karena report |

---

### PATCH /api/notifications/{notification}/read 🔐

Tandai satu notifikasi sudah dibaca.

**Response** `200`:

```json
{ "message": "Notifikasi ditandai sudah dibaca." }
```

---

### PATCH /api/notifications/read-all 🔐

Tandai semua notifikasi sudah dibaca.

**Response** `200`:

```json
{ "message": "Semua notifikasi ditandai sudah dibaca." }
```

**Response** `200`:

```json
{ "message": "Notifikasi dihapus." }
```

---

### DELETE /api/notifications/{notification} 🔐

Hapus satu notifikasi.

---

### DELETE /api/notifications/read 🔐

Hapus semua notifikasi yang sudah dibaca.

**Response** `200`:

```json
{ "message": "Semua notifikasi yang sudah dibaca dihapus." }
```

---

## J. REPORTS

### POST /api/reports 🔐

Laporkan post atau comment.

> ❌ Tidak bisa report konten sendiri.
> ❌ Tidak bisa report konten yang sama 2x (selama status `pending`/`reviewed`).

**Body:**

```json
{
    "target_type": "post",
    "target_id": "uuid",
    "reason": "spam",
    "description": "Opsional, max 500 chars"
}
```

**Reason values:** `spam`, `harassment`, `misinformation`, `inappropriate`, `other`

**Response** `201`:

```json
{ "message": "Laporan berhasil dikirim." }
```

---

### GET /api/reports 🔐 (Mod/Admin only)

Daftar semua report.

**Query:** `?status=pending&target_type=post`

**Response** `200` — paginated

---

### GET /api/reports/{report} 🔐 (Mod/Admin only)

Detail report + target konten.

**Response** `200`:

```json
{
  "data": { "report object with reporter, resolver" },
  "target": { "post or comment object" }
}
```

---

### PATCH /api/reports/{report}/resolve 🔐 (Mod/Admin only)

Setujui atau tolak report.

> ❌ Report sudah `resolved`/`dismissed` tidak bisa diubah.

**Body:**

```json
{ "status": "resolved" }
```

**Status values:** `resolved`, `dismissed`

**Jika `resolved`:**

- Post → soft delete (`status = deleted`)
- Comment dengan replies → soft delete (body jadi `[komentar telah dihapus]`)
- Comment tanpa replies → hard delete
- Pemilik konten kena **-10 points** (`content_reported`)
- Pelapor dapat notifikasi `report_confirmed`
- Pemilik konten dapat notifikasi `report_penalized`

**Response** `200`:

```json
{ "message": "Report disetujui, konten dihapus dan poin pembuat dikurangi.", "data": { "updated report" } }
```

atau

```json
{ "message": "Report ditolak.", "data": { "updated report" } }
```

---

## K. SEARCH

### GET /api/search?q=keyword (Publik)

Global search — posts, users, tags, categories. Masing-masing maksimal 5 hasil.

**Response** `200`:

```json
{
  "query": "laravel",
  "data": {
    "posts": [...],
    "users": [...],
    "tags": [...],
    "categories": [...]
  }
}
```

---

### GET /api/search/posts?q=keyword (Publik)

Cari post dengan filter.

**Query:** `?q=laravel&category_id=uuid&tag_id=uuid&category_slug=...&tag_slug=...&user_id=uuid&is_answered=true&sort=latest&page=1`

**Response** `200` — paginated

---

### GET /api/search/users?q=keyword (Publik)

Cari user.

**Response** `200` — paginated, include `posts_count` + `followers_count`

---

### GET /api/search/tags?q=keyword (Publik)

Cari tag.

**Response** `200` — paginated, 20 per page

---

## L. CATEGORIES

### GET /api/categories (Publik)

Semua kategori root (parent_id null), include children.

**Response** `200`:

```json
{
  "data": [
    { "id": "uuid", "name": "Web Development", "slug": "web-development", "description": "...", "parent_id": null, "children": [...] }
  ]
}
```

---

### GET /api/categories/{category} (Publik)

Detail kategori + children + 10 post terbaru.

**Response** `200`:

```json
{ "data": { "category with children, posts" } }
```

---

### POST /api/categories 🔐 (Admin only)

Buat kategori baru.

**Body:**

```json
{
    "name": "Category Name",
    "description": "...",
    "parent_id": "uuid"
}
```

> `parent_id` optional untuk sub-category.

**Response** `201`

---

### PUT /api/categories/{category} 🔐 (Admin only)

Edit kategori.

> ❌ Tidak bisa set `parent_id` ke diri sendiri.

**Response** `200`

---

### DELETE /api/categories/{category} 🔐 (Admin only)

Hapus kategori.

> ❌ Tidak bisa hapus kalau masih ada post.
> Children dipindah ke root (`parent_id = null`).

**Response** `200`

---

## M. TAGS

### GET /api/tags (Publik)

Semua tag, urut `usage_count` desc.

**Query:** `?search=laravel&page=1`

**Response** `200` — paginated

---

### GET /api/tags/{tag} (Publik)

Detail tag + 10 post terbaru.

**Response** `200`:

```json
{ "data": { "tag with posts" } }
```

---

### POST /api/tags 🔐

Buat tag baru. **Endpoint ini tidak memiliki guard** — semua user terautentikasi bisa membuat tag.

**Body:**

```json
{
    "name": "ReactJS",
    "color": "#61DAFB"
}
```

> `color` optional, format hex `#RRGGBB`.

**Response** `201`

---

### PUT /api/tags/{tag} 🔐 (Mod/Admin only)

Edit tag.

**Response** `200`

---

### DELETE /api/tags/{tag} 🔐 (Mod/Admin only)

Hapus tag. Detach dari semua post.

**Response** `200`

---

## N. POINTS

### GET /api/me/points 🔐

History poin sendiri + summary.

**Query:** `?action_type=content_upvoted&type=earn` (opsional filter)

**Response** `200`:

```json
{
  "summary": {
    "current_points": 10001,
    "total_earned": 2,
    "total_deducted": 0
  },
  "data": { "paginated points_log" }
}
```

---

### GET /api/users/{userId}/points 🔐 (Admin only)

History poin user tertentu.

---

### POST /api/users/{userId}/points/recalculate 🔐 (Admin only)

Recalculate `reputation_points` dari total `points_log`.

**Response** `200`:

```json
{ "message": "Points berhasil direcalculate.", "reputation_points": 10001 }
```

---

## O. ERROR CODES

| HTTP | Arti                                                           |
| ---- | -------------------------------------------------------------- |
| 200  | OK                                                             |
| 201  | Created                                                        |
| 400  | Bad Request                                                    |
| 401  | Unauthenticated (token salah/kadaluarsa/tidak dikirim)         |
| 403  | Forbidden (bukan owner/admin/moderator)                        |
| 404  | Not Found                                                      |
| 405  | Method Not Allowed                                             |
| 422  | Validation Error / Business Rule (point kurang, duplikat, dll) |
| 500  | Internal Server Error                                          |

---

## P. POINT SYSTEM

| Aksi                       | Point                  | Action Type         |
| -------------------------- | ---------------------- | ------------------- |
| Konten di-upvote           | +2 (owner)             | `content_upvoted`   |
| Memberi downvote           | -1 (voter)             | `downvote_given`    |
| Konten di-downvote         | -2 (owner)             | `content_downvoted` |
| Upvote dibatalkan          | -2 (owner)             | `upvote_removed`    |
| Downvote dibatalkan        | +1 (voter), +2 (owner) | `downvote_removed`  |
| Jawaban diterima           | +10 (penulis)          | `answer_accepted`   |
| Konten di-report & dihapus | -10 (owner)            | `content_reported`  |

> Poin minimal: **0** (tidak bisa negatif, dicek di PointService).

---

## Q. ROLE & PERMISSION

| Role        | Kemampuan                                                                   |
| ----------- | --------------------------------------------------------------------------- |
| `user`      | CRUD post sendiri, komentar, vote, bookmark, follow, report, **buat tag**   |
| `moderator` | User + hapus post/comment siapapun, kelola tag, resolve report              |
| `admin`     | Moderator + kelola kategori, lihat history post/comment, recalculate points |

**Helper methods di User model:**

- `$user->isAdmin()` — cek role admin
- `$user->isModerator()` — cek role moderator
- `$user->isModeratorOrAdmin()` — cek moderator atau admin

**Level titles (otomatis berdasarkan `reputation_points`):**

| Level | Title       | Min Points |
| ----- | ----------- | ---------- |
| 1     | Newbie      | 0          |
| 2     | Member      | 15         |
| 3     | Contributor | 20         |
| 4     | Warrior     | 30         |
| 5     | Elite       | 40         |
| 6     | Master      | 50         |
| 7     | Grandmaster | 60         |
| 8     | Epic        | 70         |
| 9     | Legend      | 80         |
| 10    | Mythic      | 90         |
