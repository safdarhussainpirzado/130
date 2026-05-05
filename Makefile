.PHONY: build up down restart test fresh optimize bash logs migrate-legacy

# Deploy Production Image locally
build:
	docker-compose build

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
	
	@echo "Step 2 & 3: Extracting Legacy Schema & Data into Staging..."
	@rm -f staging_raw.sql
	@for table in calls call_status_history users user_scopes beats sectors zones call_types call_sub_types vehicle_types regions; do \
		echo "  -> Extracting $$table..." ; \
		sed -n "/-- Table structure for table \`$$table\`/,/-- Table structure for table/p" Dump20260504_legacy.sql | grep -v "\-\- Table structure for table \`" >> staging_raw.sql || true ; \
	done
	
	@# Rename target tables to legacy_* in all contexts (DROP, CREATE, INSERT, etc.)
	@for t in calls call_status_history users user_scopes beats sectors zones call_types call_sub_types vehicle_types regions; do \
		sed -i "s/\`$$t\`/\`legacy_$$t\`/gI" staging_raw.sql ; \
		sed -i "s/ $$t / legacy_$$t /gI" staging_raw.sql ; \
	done
	@sed -i "s/INSERT INTO/INSERT IGNORE INTO/gI" staging_raw.sql
	
	@# Remove constraints, locking, and multi-line syntax issues
	@sed -i '/LOCK TABLES/d' staging_raw.sql
	@sed -i '/UNLOCK TABLES/d' staging_raw.sql
	@sed -i '/CONSTRAINT/d' staging_raw.sql
	@sed -i '/FOREIGN KEY/d' staging_raw.sql
	@sed -i '/REFERENCES/d' staging_raw.sql
	@perl -i -0777 -pe 's/,\s*\)/\n\)/g' staging_raw.sql
	
	@echo "Step 3: Importing legacy data into Staging..."
	@(echo "SET SESSION sql_mode = ''; SET FOREIGN_KEY_CHECKS=0;"; cat staging_raw.sql) | docker-compose exec -T db mysql -f -u root -psecret 130_crm_db
	@rm staging_raw.sql

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
