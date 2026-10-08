local-migration:
	docker exec -it moodle-db mysql -u moodleuser -pmoodlepass moodledb < moodle-db-2025-10-10_00-00-40.sql

compose-up:
	docker compose up -d

run:
	@echo "Starting LMS"
	@echo "Running Docker Compose..."
	$(MAKE) compose-up
	@echo "Running Migrations..."
	$(MAKE) local-migration

inspect-db:
	docker exec -it moodle-db mysql -u moodleuser -pmoodlepass moodledb
