-- ---------------------------------------------------------------------------
-- Member self-service portal: authentication
--
-- The portal lets a member sign in and read their own financial records. It
-- writes nothing to the financial tables; these two tables are all it owns.
--
-- Identity at registration is proven by two factors the member must supply
-- together: their name (chosen from a search that deliberately does NOT show
-- the ID) and their payslip number, which is tbl_personalinfo.patientid.
-- ---------------------------------------------------------------------------

-- Credentials. One row per member who has completed registration.
CREATE TABLE IF NOT EXISTS tbl_member_auth (
    membersid       INT          NOT NULL,
    email           VARCHAR(190) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    status          ENUM('active','suspended') NOT NULL DEFAULT 'active',
    failed_attempts INT          NOT NULL DEFAULT 0,
    locked_until    DATETIME         NULL,
    last_login_at   DATETIME         NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (membersid),
    UNIQUE KEY uniq_member_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One-time codes for registration and password reset.
-- Codes are stored hashed: a leaked table must not hand out working OTPs.
CREATE TABLE IF NOT EXISTS tbl_member_otp (
    id          INT          NOT NULL AUTO_INCREMENT,
    membersid   INT          NOT NULL,
    purpose     ENUM('register','reset') NOT NULL,
    email       VARCHAR(190) NOT NULL,
    otp_hash    VARCHAR(255) NOT NULL,
    expires_at  DATETIME     NOT NULL,
    attempts    INT          NOT NULL DEFAULT 0,
    consumed_at DATETIME         NULL,
    ip          VARCHAR(45)      NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_member_purpose (membersid, purpose),
    KEY idx_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Throttling for name/ID guessing and login attempts, keyed by IP + action.
CREATE TABLE IF NOT EXISTS tbl_member_throttle (
    id         INT         NOT NULL AUTO_INCREMENT,
    ip         VARCHAR(45) NOT NULL,
    action     VARCHAR(40) NOT NULL,
    created_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ip_action_time (ip, action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
