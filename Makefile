.PHONY: build up down restart test fresh optimize bash logs migrate-legacy

# Deploy Production Image locally
build:
	docker-compose build --no-cache

# Setup Environment
up:
	docker-compose up -d

# Spin down
down:
	docker-compose down

# Restart
restart: down up

# Run DB Migrations & Reset
fresh:
	docker-compose exec app php artisan migrate:fresh --seed --force
	docker-compose exec app php artisan optimize:clear

# Full Legacy Migration Process (Structural -> Import -> Mapping)
migrate-legacy:
	@echo "Step 1: Setting up fresh structural database..."
	docker-compose exec app php artisan migrate:fresh --force -v
	docker-compose exec app php artisan db:seed --class=ProductionSeeder --force -v
	
	@echo "Step 2: Creating Legacy Staging Tables..."
	@docker-compose exec -T db mysql -u root -psecret 130_crm_db -e "CREATE TABLE legacy_calls LIKE calls; ALTER TABLE legacy_calls ADD COLUMN zone_id INT, ADD COLUMN sector_id INT, ADD COLUMN beat_id INT AFTER km_marker_text; ALTER TABLE legacy_calls DROP COLUMN office_id;"
	@docker-compose exec -T db mysql -u root -psecret 130_crm_db -e "CREATE TABLE legacy_call_status_history LIKE call_status_history;"
	@docker-compose exec -T db mysql -u root -psecret 130_crm_db -e "CREATE TABLE legacy_beats (id INT, sector_id INT, zone_id INT, name VARCHAR(255), code VARCHAR(50), status VARCHAR(20), created_at TIMESTAMP, updated_at TIMESTAMP, deleted_at TIMESTAMP);"

	@echo "Step 3: Importing legacy data into Staging..."
	@grep -a -iE "INSERT INTO \`(calls|call_status_history|beats)\`|INSERT INTO (calls|call_status_history|beats) " Dump20260502_legacy.sql > data_only.sql || true
	@sed -i "s/INSERT INTO \`calls\`/INSERT INTO \`legacy_calls\`/gI" data_only.sql
	@sed -i "s/INSERT INTO \`call_status_history\`/INSERT INTO \`legacy_call_status_history\`/gI" data_only.sql
	@sed -i "s/INSERT INTO \`beats\`/INSERT INTO \`legacy_beats\`/gI" data_only.sql
	@sed -i "s/INSERT INTO calls/INSERT INTO legacy_calls/gI" data_only.sql
	@sed -i "s/INSERT INTO call_status_history/INSERT INTO legacy_call_status_history/gI" data_only.sql
	@sed -i "s/INSERT INTO beats/INSERT INTO legacy_beats/gI" data_only.sql
	@sed -i 's/INSERT INTO/INSERT IGNORE INTO/gI' data_only.sql
	@(echo "SET SESSION sql_mode = '';"; cat data_only.sql) | docker-compose exec -T db mysql -f -u root -psecret 130_crm_db
	@rm data_only.sql

	@echo "Step 4: Mapping categories, offices and converting timestamps (GMT -> PKT)..."
	docker-compose exec app php artisan migrate --path=database/migrations/legacy_fixes --force -v
	
	@echo "Step 5: Finalizing system categories..."
	docker-compose exec app php artisan db:seed --class=NHMPCategorySeeder --force -v
	
	@echo "Step 6: Clearing caches..."
	docker-compose exec app php artisan optimize:clear
	@echo "Migration Process Complete!"

# Optimize caches for high production performance
optimize:
	docker-compose exec app php artisan optimize
	docker-compose exec app php artisan view:cache
	docker-compose exec app php artisan event:cache

# Follow logs realistically
logs:
	docker-compose logs -f app

# Exec inside Container
bash:
	docker-compose exec app sh
