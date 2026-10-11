-- Apply once before deploying bot-only game code to an existing installation.
ALTER TABLE wD_Games ADD COLUMN allowAllBots enum('No','Yes') NOT NULL DEFAULT 'No';
ALTER TABLE wD_Backup_Games ADD COLUMN allowAllBots enum('No','Yes') NOT NULL DEFAULT 'No';
