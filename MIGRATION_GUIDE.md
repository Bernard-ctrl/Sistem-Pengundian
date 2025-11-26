# Migration Guide: Database Schema Update

## Database Changes

### Old Schema (Before)
- `users` - user authentication
- `votes` - aggregated vote counts
- `user_votes` - tracking user votes

### New Schema (After)
- `PENGGUNA` - User information with ID, name, password, admin flag
- `CALON` - Candidate information (ID and name)
- `JAWATAN` - Position/role information (ID and name)
- `UNDIAN` - Individual vote records with foreign keys

## Key Changes

### 1. Database Structure (`database.sql`)
✅ Complete schema matching the ERD requirements
✅ Foreign key constraints for data integrity
✅ Sample data from the provided image
✅ Unique constraint: one vote per user per position

### 2. Backend (`index.php`)
✅ Updated to use PENGGUNA, CALON, JAWATAN, UNDIAN tables
✅ User registration now stores ID and name
✅ Voting uses IDs instead of names
✅ New API endpoints:
   - `GET ?action=get_jawatan` - fetch all positions
   - `GET ?action=get_calon` - fetch all candidates
   - `GET ?action=my_votes` - user's voted positions
   - `GET ?action=view_users` - admin: view all users
   - `GET ?action=view_votes` - admin: view vote summary

### 3. Frontend (`index.html`)
✅ Signup form now includes "Nama Penuh" field
✅ Login forms use "ID Pengguna" instead of "Username"
✅ Position dropdown dynamically loaded from database
✅ All candidates shown for each position

### 4. JavaScript (`script.js`)
✅ Fetches positions and candidates from server
✅ Submits vote using id_jawatan and id_calon
✅ Dynamic form population

### 5. Admin (`admin.html`)
✅ Updated labels to match new terminology

## How to Use

### Step 1: Setup Database
```bash
# Import the database schema
mysql -u root -p < database.sql
```

Or manually:
1. Open phpMyAdmin or MySQL Workbench
2. Run the SQL file `database.sql`
3. This creates the database and sample data

### Step 2: Test the Application
1. Open `index.html` in your browser
2. Register a new user (provide ID and Name)
3. Login with your credentials
4. Vote for candidates

### Step 3: Admin Access
- Default admin credentials:
  - ID: `admin`
  - Password: `admin`
- Change the admin password after first login!

## Default Data Included

### Positions (JAWATAN)
- J01 - Pengerusi
- J02 - Setiausaha
- J03 - Bendahari

### Candidates (CALON)
- C01 - Omar
- C02 - Hassan
- C03 - Aiman

### Sample Users (PENGGUNA)
- D6261 - Ali
- D6262 - Abu
- D6263 - Ahmad
- D6264 - James

## Important Notes

⚠️ **Breaking Changes:**
- All existing user data will be lost
- You need to re-import the database
- Users need to re-register with ID format (e.g., D6261)

✅ **Benefits:**
- Proper database normalization
- Foreign key constraints
- Easier to manage candidates and positions
- Better data integrity
- Matches your ERD requirements exactly

## Adding New Data

### Add New Position
```sql
INSERT INTO JAWATAN (id_Jawatan, nama_Jawatan) VALUES ('J04', 'Naib Pengerusi');
```

### Add New Candidate
```sql
INSERT INTO CALON (id_Calon, nama_Calon) VALUES ('C04', 'Nurul');
```

### Add New User
Use the signup form on the website, or manually:
```sql
INSERT INTO PENGGUNA (id_Pengguna, nama, password, is_admin) 
VALUES ('D6265', 'Sarah', '$2y$10$...hashed_password...', 0);
```

## Troubleshooting

**Q: Can't login after migration?**
A: Re-register your account. All old user data is not compatible.

**Q: No positions or candidates showing?**
A: Run `database.sql` to populate default data.

**Q: Foreign key constraint errors?**
A: Ensure you're using valid IDs that exist in CALON and JAWATAN tables.

## Database Schema Diagram

```
PENGGUNA                 UNDIAN                  CALON
┌─────────────┐         ┌──────────────┐        ┌──────────────┐
│id_Pengguna  │◄────────│id_Pengguna   │        │id_Calon      │
│nama         │         │id_Calon      │───────►│nama_Calon    │
│password     │         │id_Jawatan    │        └──────────────┘
│is_admin     │         │id_Undi (PK)  │
└─────────────┘         └──────────────┘
                               │
                               │
                               ▼
                        JAWATAN
                        ┌──────────────┐
                        │id_Jawatan    │
                        │nama_Jawatan  │
                        └──────────────┘
```

## Compliance Status

✅ **PENGGUNA table** - Matches schema (id_Pengguna, nama)
✅ **CALON table** - Matches schema (id_Calon, nama_Calon)
✅ **JAWATAN table** - Matches schema (id_Jawatan, nama_Jawatan)
✅ **UNDIAN table** - Matches schema (id_Undi, id_Pengguna, id_Calon, id_Jawatan)
✅ **Foreign Keys** - Properly implemented
✅ **Sample Data** - Matches your provided image