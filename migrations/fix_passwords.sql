UPDATE admins SET password = '$2y$10$j7fxR3DRy2TlKWSgpwSW0eR/YXWh4.L.Yn8HESvCtoluyJzQt.6JG' WHERE id = 1;
UPDATE admins SET password = '$2y$10$fZkoWur03oLhc8gQbjzjf.wUOeCy.kYL4p7JY.iRUHwoC0FjAL9Q6' WHERE id = 2;
SELECT id, full_name, email, LENGTH(password) AS pw_len, LEFT(password,7) AS pw_prefix FROM admins;
