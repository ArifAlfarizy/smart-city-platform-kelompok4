ALTER TABLE users
  MODIFY COLUMN role ENUM(
    'citizen',
    'operator',
    'dishub',
    'command_center',
    'operator_tmc'
  ) NOT NULL DEFAULT 'citizen';