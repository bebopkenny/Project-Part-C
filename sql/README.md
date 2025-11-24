# Logical Design and Database Implementation for Theatre Booking Database

## 1. Overview

### Introduction
This phase of the project applies the design of a cinema theatre booking system to MariaDB or MySQL. It uses the same entities and assumptions established in Part A: theatre, auditorium, seat, movie, showtime, customer, and ticket.

Part B comprises:
- DDL table creation scripts
- DML seed data scripts
- Views
- Triggers
- A stored procedure for ticket sales
- A procedure for backing up which clones all primary tables

## 2. Prerequisite

In order to use this project, you will need:
- MariaDB or MySQL server, installed and running
- A SQL client tool command line or GUI based
- The Part B SQL files, specifically:
  - DDL script that creates all tables and constraints
  - DML seed script that inserts demo data
  - Script that creates views, triggers, and the ticket selling procedure
  - Backup script that creates a full clone of the main tables

You can name these files anything you and your group would like. This README will refer to them generally as:
- `ddl.sql`
- `seed.sql`
- `views_triggers_procs.sql`
- `backup.sql`

## 3. Database Setup

Your first step is to create the database and select it to use. For example: `CREATE DATABASE theatre_booking` and `USE theatre_booking`. All of these SQL scripts presumes you are in this database.

### 3.1 Run the DDL script tables and constraints

Then you will run your DDL script. For example `ddl.sql`. The script should include:

Create all base tables:
- Theatre
- Auditorium
- Seat
- Movie
- Showtime
- Customer
- Ticket

The DDL also defines primary keys, foreign keys, and ENUM columns to match the relational schema from Part A.

### 3.2 Execute the DML seed script

Next execute the DML seed script, that defines and calls the procedure `seed_demo_data` with the following behavior:

- Deletes any existing rows in `ticket_audit`, `ticket`, `customer`, `showtime`, `seat`, `auditorium`, `movie`, and `theatre` in a safe order.
- Inserts 3 theatres with realistic addresses.
- Inserts 10 auditoriums (with different rows and seats) across those theatres.
- It iterates over three auditorium layouts (8x12, 10x16, 12x20) to create full seat maps that assign one of three seat types (STANDARD, PREMIUM and ADA) by position.
- Inserts 12 movies with their titles, runtimes, MPAA ratings and release dates.
- Inserts 80 showtimes rotating through movies and auditoriums, over multiple days, at different times and formats (2D, 3D, IMAX) in different languages (SUB/DUB), base prices between 15-19
- Inserts 60 customers with simple "Customer N" customer names & emails.
- Inserts 400 tickets in total (5 per showtime for the first 80 showtimes) with discount types of NONE, STUDENT, and SENIOR.

After the first call to `seed_demo_data`, the script drops that procedure from the database.

### 3.3 Create Views, Triggers, and a Stored Procedure

Following the execution of the seed script, execute the script that creates the views, triggers, and stored procedure related to ticket sales (for example, `Views_triggers_procs.sql`). This script assumes that you have already executed `USE theatre_booking`.

The views created here are:

**`vw_top_movies_last_30_days`**  
This view provides a list of each movie along with the total number of tickets sold in the past 30 days, based on showtime start time and ticket status.

**`vw_upcoming_sold_out_showtimes`**  
This view lists upcoming sold out showtimes based on a comparison of the total number of tickets to the full capacity of the auditorium.

**`vw_theatre_utilization_next_7_days`**  
This view reports details such as the theatre name, showtime, the total number of seats available in each auditorium, the number of seats sold, and the percentage of seats sold for showtimes happening in the next 7 days.

**Triggers:**

**`trg_ticket_before_insert`**  
This trigger runs first when inserting the ticket to verify if there is already a ticket for the same seat and showtime with status RESERVED, PURCHASED, or USED; if there is, an error is raised to prevent double selling the seat.

**`trg_ticket_after_insert`**  
After the ticket has been inserted, write an audit entry into `ticket_audit` with the action "INSERT".

**`trg_ticket_after_update_refunded`**  
After the ticket has been updated, if the status has changed to REFUNDED, it adds an audit entry into `ticket_audit` with action "REFUND", for tracking refunds and setting the seat free again for availability checks.

**Stored procedure (`sell_ticket`):**

`sell_ticket(showtime_id, seat_id, customer_id, discount_code, OUT ticket_id)`
- Makes sure that the seat is in the auditorium for the showtime.
- Checks no ticket exists for that showtime and seat where the status is reserved/purchased/used.
- Pulls down the showtime's base price, pulls the seat_type for the seat, and modifies the price if the seat is PREMIUM or ADA.
- If the discount_code is not null, apply extra discount for STUDENT, SENIOR, CHILD, etc.
- Inserts a new row ticket row with an updated status of PURCHASED, the final price, and returns the new TicketID in the OUT parameter.

### 3.4 Execute the backup script

Finally, run the backup script which is the `backup.sql` for our program. The script defines a stored procedure called `backup_full_clone` which:
- Builds a prefix for the backup table name, which begins with `back_` and includes the current date.
- For each of the main tables (`theatre`, `auditorium`, `seat`, `movie`, `showtime`, `customer`, `ticket`):
  - It drops the backup table with that date prefix if it already exists.
  - It creates a backup table as `CREATE TABLE back_YYYYMMDD_tableName AS SELECT * FROM tableName`.

Once the procedure is created, the script performs a call to the following procedure:

```sql
CALL backup_full_clone();
```

This creates a snapshot copy of all core tables and is used as a demonstration of database tables backup.

## 4. Testing and sample queries

To demonstrate the database has been loaded correctly and the features of Part B of this project are functional, the following sample queries can be used.

### 4.1 Basic row count checks

These queries confirm the seed script had inserted the appropriate amount of data:

```sql
SELECT COUNT(*) FROM theatre;
SELECT COUNT(*) FROM auditorium;
SELECT COUNT(*) FROM seat;
SELECT COUNT(*) FROM movie;
SELECT COUNT(*) FROM showtime;
SELECT COUNT(*) FROM customer;
SELECT COUNT(*) FROM ticket;
```

The counts should meet the project requirements: 3 theatres, 10 auditoriums, full seat maps, 12 movies, 80 showtimes, 60 customers, 400 tickets.

### 4.2 Checking Views

Here are some examples of how to query the views:

```sql
SELECT *
FROM vw_top_movies_last_30_days
ORDER BY tickets_sold DESC
LIMIT 10;
```

```sql
SELECT *
FROM vw_upcoming_sold_out_showtimes
ORDER BY start_time;
```

```sql
SELECT *
FROM vw_theatre_utilization_next_7_days
ORDER BY start_time;
```

### 4.3 Checking Stored Procedures and Triggers

To verify `sell_ticket` and the triggers:

Sell a ticket for a certain customer, showtime, and seat:

```sql
SET @new_ticket_id = NULL;
CALL sell_ticket(1, 1, 1, 'STUDENT', @new_ticket_id);
SELECT @new_ticket_id;

SELECT * FROM ticket WHERE ticket_id = @new_ticket_id;
SELECT * FROM ticket_audit WHERE ticket_id = @new_ticket_id;
```

You should see a row in the ticket table with a reduced price and a corresponding row in the audit table with action "INSERT".

Try to sell a ticket for the same seat for the same showtime:

```sql
CALL sell_ticket(1, 1, 1, 'STUDENT', @new_ticket_id);
```

This should result in an error saying that the seat is already sold or reserved for this showtime, confirming that `trg_ticket_before_insert` is working.

Verifying the refund process:

```sql
UPDATE ticket
SET status = 'REFUNDED'
WHERE ticket_id = @new_ticket_id;

SELECT * FROM ticket_audit WHERE ticket_id = @new_ticket_id;
```

You should now see an additional audit row with the action 'REFUND'.

### 4.4 Verifying the backup procedure

```sql
CALL backup_full_clone;
SHOW TABLES LIKE 'back_%';
```