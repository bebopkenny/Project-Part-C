Views (Reports):

USE theatre_booking;

-- 1. Top-N movies by tickets sold in the last 30 days
CREATE OR REPLACE VIEW vw_top_movies_last_30_days AS
SELECT
  m.movie_id,
  m.name       AS movie_name,
  COUNT(CASE WHEN t.status IN ('PURCHASED','USED') THEN 1 END) AS tickets_sold
FROM movie m
JOIN showtime s   ON s.movie_id = m.movie_id
LEFT JOIN ticket t ON t.showtime_id = s.showtime_id
WHERE s.start_time >= NOW() - INTERVAL 30 DAY
GROUP BY m.movie_id, m.name;

-- Example query for top 10:
-- SELECT * FROM vw_top_movies_last_30_days
-- ORDER BY tickets_sold DESC
-- LIMIT 10;


-- 2. Upcoming sold-out showtimes per theatre
CREATE OR REPLACE VIEW vw_upcoming_sold_out_showtimes AS
SELECT
  th.theatre_id,
  th.name         AS theatre_name,
  s.showtime_id,
  m.name          AS movie_name,
  s.start_time
FROM showtime s
JOIN movie m       ON m.movie_id = s.movie_id
JOIN auditorium a  ON a.auditorium_id = s.auditorium_id
JOIN theatre th    ON th.theatre_id = a.theatre_id
JOIN (
  SELECT showtime_id,
         COUNT(*) AS seats_sold
  FROM ticket
  WHERE status IN ('RESERVED','PURCHASED','USED')
  GROUP BY showtime_id
) t ON t.showtime_id = s.showtime_id
JOIN (
  SELECT auditorium_id,
         COUNT(*) AS total_seats
  FROM seat
  GROUP BY auditorium_id
) seat_counts ON seat_counts.auditorium_id = a.auditorium_id
WHERE s.start_time >= NOW()
  AND t.seats_sold >= seat_counts.total_seats;


-- 3. Theatre utilization report: % seats sold per showtime next 7 days
CREATE OR REPLACE VIEW vw_theatre_utilization_next_7_days AS
SELECT
  th.theatre_id,
  th.name            AS theatre_name,
  s.showtime_id,
  m.name             AS movie_name,
  s.start_time,
  seat_counts.total_seats,
  COALESCE(sales.seats_sold, 0) AS seats_sold,
  ROUND(COALESCE(sales.seats_sold, 0) / seat_counts.total_seats * 100, 1) AS pct_sold
FROM showtime s
JOIN movie m       ON m.movie_id = s.movie_id
JOIN auditorium a  ON a.auditorium_id = s.auditorium_id
JOIN theatre th    ON th.theatre_id = a.theatre_id
JOIN (
  SELECT auditorium_id, COUNT(*) AS total_seats
  FROM seat
  GROUP BY auditorium_id
) seat_counts ON seat_counts.auditorium_id = a.auditorium_id
LEFT JOIN (
  SELECT showtime_id,
         COUNT(*) AS seats_sold
  FROM ticket
  WHERE status IN ('RESERVED','PURCHASED','USED')
  GROUP BY showtime_id
) sales ON sales.showtime_id = s.showtime_id
WHERE s.start_time >= NOW()
  AND s.start_time < NOW() + INTERVAL 7 DAY;

Triggers:

USE theatre_booking;

DELIMITER $$

CREATE TRIGGER trg_ticket_before_insert
BEFORE INSERT ON ticket
FOR EACH ROW
BEGIN
  DECLARE seat_taken INT;

  SELECT COUNT(*)
  INTO seat_taken
  FROM ticket
  WHERE showtime_id = NEW.showtime_id
    AND seat_id     = NEW.seat_id
    AND status IN ('RESERVED','PURCHASED','USED');

  IF seat_taken > 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Seat already sold or reserved for this showtime';
  END IF;
END$$

DELIMITER ;

Stored Procedure (sell_ticket):

USE theatre_booking;

DELIMITER $$

CREATE PROCEDURE sell_ticket(
  IN  p_showtime_id  INT,
  IN  p_seat_id      INT,
  IN  p_customer_id  INT,
  IN  p_discount_code VARCHAR(64),
  OUT p_ticket_id    INT
)
BEGIN
  DECLARE v_exists INT;
  DECLARE v_base_price DECIMAL(6,2);
  DECLARE v_seat_type VARCHAR(10);
  DECLARE v_price DECIMAL(6,2);

  -- Check seat belongs to the same auditorium as the showtime
  SELECT COUNT(*)
  INTO v_exists
  FROM showtime st
  JOIN seat se ON se.auditorium_id = st.auditorium_id
  WHERE st.showtime_id = p_showtime_id
    AND se.seat_id = p_seat_id;

  IF v_exists = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Seat does not belong to this showtime auditorium';
  END IF;

  SELECT COUNT(*)
  INTO v_exists
  FROM ticket
  WHERE showtime_id = p_showtime_id
    AND seat_id     = p_seat_id
    AND status IN ('RESERVED','PURCHASED','USED');

  IF v_exists > 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Seat already sold or reserved';
  END IF;

  SELECT st.base_price, se.seat_type
  INTO v_base_price, v_seat_type
  FROM showtime st
  JOIN seat se ON se.auditorium_id = st.auditorium_id
  WHERE st.showtime_id = p_showtime_id
    AND se.seat_id = p_seat_id;

  SET v_price = v_base_price;

  -- Seat type adjustment
  IF v_seat_type = 'PREMIUM' THEN
    SET v_price = v_price * 1.20;
  ELSEIF v_seat_type = 'ADA' THEN
    SET v_price = v_price * 0.90;
  END IF;

  -- Discount code adjustment
  IF p_discount_code IS NOT NULL THEN
    CASE UPPER(p_discount_code)
      WHEN 'STUDENT' THEN SET v_price = v_price * 0.80;
      WHEN 'SENIOR'  THEN SET v_price = v_price * 0.85;
      WHEN 'CHILD'   THEN SET v_price = v_price * 0.75;
      ELSE
        SET v_price = v_price;
    END CASE;
  END IF;

  -- Insert ticket
  INSERT INTO ticket (showtime_id, seat_id, customer_id, price, discount_type, status)
  VALUES (p_showtime_id, p_seat_id, p_customer_id, v_price, p_discount_code, 'PURCHASED');

  SET p_ticket_id = LAST_INSERT_ID();

  SELECT p_ticket_id AS ticket_id;
END$$

DELIMITER ;

