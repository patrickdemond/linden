DROP PROCEDURE IF EXISTS patch_qnaire;
DELIMITER //
CREATE PROCEDURE patch_qnaire()
  BEGIN

    SELECT "Adding anonymous column to qnaire table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire"
    AND column_name = "anonymous";

    IF @test = 0 THEN
      ALTER TABLE qnaire ADD COLUMN anonymous TINYINT(1) NOT NULL DEFAULT 0 AFTER readonly;
    END IF;

    SELECT "Adding show_progress column to qnaire table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire"
    AND column_name = "show_progress";

    IF @test = 0 THEN
      ALTER TABLE qnaire ADD COLUMN show_progress TINYINT(1) NOT NULL DEFAULT 1 AFTER anonymous;
    END IF;

    SELECT "Removing beartooth_url column from qnaire table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire"
    AND column_name = "beartooth_url";

    IF @test = 1 THEN
      ALTER TABLE qnaire DROP COLUMN beartooth_url;
    END IF;

    SELECT "Removing beartooth_username column from qnaire table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire"
    AND column_name = "beartooth_username";

    IF @test = 1 THEN
      ALTER TABLE qnaire DROP COLUMN beartooth_username;
    END IF;

    SELECT "Removing beartooth_password column from qnaire table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire"
    AND column_name = "beartooth_password";

    IF @test = 1 THEN
      ALTER TABLE qnaire DROP COLUMN beartooth_password;
    END IF;

    SELECT "Adding parent_beartooth_url column to qnaire table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire"
    AND column_name = "parent_beartooth_url";

    IF @test = 0 THEN
      ALTER TABLE qnaire ADD COLUMN parent_beartooth_url VARCHAR(255) NULL DEFAULT NULL AFTER email_invitation;
    END IF;

    SELECT "Removing beartooth column to qnaire table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire"
    AND column_name = "beartooth";

    IF @test = 1 THEN
      ALTER TABLE qnaire DROP COLUMN beartooth;
    END IF;

    SELECT "Adding parent_username column to qnaire table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire"
    AND column_name = "parent_username";

    IF @test = 0 THEN
      ALTER TABLE qnaire ADD COLUMN parent_username VARCHAR(45) NULL DEFAULT NULL AFTER parent_beartooth_url;
    END IF;

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire"
    AND column_name = "parent_password";

    IF @test = 0 THEN
      ALTER TABLE qnaire ADD COLUMN parent_password VARCHAR(45) NULL DEFAULT NULL AFTER parent_username;
    END IF;

    SELECT "Adding appointment_type column to qnaire table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire"
    AND column_name = "appointment_type";

    IF @test = 0 THEN
      ALTER TABLE qnaire ADD COLUMN appointment_type VARCHAR(45) NULL DEFAULT NULL AFTER parent_password;
    END IF;

    SELECT "Adding attributes_mandatory column to qnaire table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire"
    AND column_name = "attributes_mandatory";

    IF @test = 0 THEN
      ALTER TABLE qnaire ADD COLUMN attributes_mandatory TINYINT(1) NOT NULL DEFAULT 0 AFTER problem_report;
    END IF;

  END //
DELIMITER ;

CALL patch_qnaire();
DROP PROCEDURE IF EXISTS patch_qnaire;
