<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Core\MigrationRunner;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class RoomMigrationTest extends DatabaseTestCase
{
    private const APPLICATION_MIGRATIONS = [
        '20260721000100_create_roles_table',
        '20260721000200_create_users_table',
        '20260722000100_create_room_types_table',
        '20260722000200_create_rooms_table',
        '20260722000300_create_room_images_table',
        '20260722000400_create_amenities_table',
        '20260722000500_create_room_amenities_table',
    ];

    private MigrationRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runner = new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        );
    }

    public function testRoomMigrationsExposeTheApprovedSchemaConstraintsAndRelationships(): void
    {
        self::assertSame(self::APPLICATION_MIGRATIONS, $this->runner->migrate());

        $roomTypes = $this->columns('room_types');
        self::assertSame([
            'id',
            'name',
            'slug',
            'description',
            'base_price',
            'capacity_adults',
            'capacity_children',
            'is_active',
            'created_at',
            'updated_at',
        ], array_keys($roomTypes));
        $this->assertBigIntUnsigned($roomTypes['id'], false, true);
        $this->assertVarchar($roomTypes['name'], 100, false);
        $this->assertVarchar($roomTypes['slug'], 120, false);
        $this->assertText($roomTypes['description'], true);
        $this->assertDecimal($roomTypes['base_price'], 12, 2, false);
        $this->assertInteger($roomTypes['capacity_adults'], false);
        $this->assertInteger($roomTypes['capacity_children'], false);
        $this->assertBoolean($roomTypes['is_active'], false);
        $this->assertTimestampColumn($roomTypes['created_at'], false);
        $this->assertTimestampColumn($roomTypes['updated_at'], true);

        $rooms = $this->columns('rooms');
        self::assertSame([
            'id',
            'room_type_id',
            'room_number',
            'name',
            'slug',
            'description',
            'price_per_night',
            'capacity_adults',
            'capacity_children',
            'operational_status',
            'is_published',
            'created_at',
            'updated_at',
            'deleted_at',
        ], array_keys($rooms));
        $this->assertBigIntUnsigned($rooms['id'], false, true);
        $this->assertBigIntUnsigned($rooms['room_type_id'], false, false);
        $this->assertVarchar($rooms['room_number'], 30, false);
        $this->assertVarchar($rooms['name'], 100, false);
        $this->assertVarchar($rooms['slug'], 120, false);
        $this->assertText($rooms['description'], true);
        $this->assertDecimal($rooms['price_per_night'], 12, 2, false);
        $this->assertInteger($rooms['capacity_adults'], false);
        $this->assertInteger($rooms['capacity_children'], false);
        $this->assertVarchar($rooms['operational_status'], 20, false);
        $this->assertBoolean($rooms['is_published'], false);
        $this->assertTimestampColumn($rooms['created_at'], false);
        $this->assertTimestampColumn($rooms['updated_at'], true);
        $this->assertNullableDateTime($rooms['deleted_at']);

        $roomImages = $this->columns('room_images');
        self::assertSame([
            'id',
            'room_id',
            'file_path',
            'alt_text',
            'sort_order',
            'is_primary',
            'created_at',
        ], array_keys($roomImages));
        $this->assertBigIntUnsigned($roomImages['id'], false, true);
        $this->assertBigIntUnsigned($roomImages['room_id'], false, false);
        $this->assertVarchar($roomImages['file_path'], 255, false);
        $this->assertVarchar($roomImages['alt_text'], 255, true);
        $this->assertInteger($roomImages['sort_order'], false);
        $this->assertBoolean($roomImages['is_primary'], false);
        $this->assertTimestampColumn($roomImages['created_at'], false);

        $amenities = $this->columns('amenities');
        self::assertSame([
            'id',
            'name',
            'icon',
            'is_active',
            'created_at',
            'updated_at',
        ], array_keys($amenities));
        $this->assertBigIntUnsigned($amenities['id'], false, true);
        $this->assertVarchar($amenities['name'], 100, false);
        $this->assertVarchar($amenities['icon'], 100, true);
        $this->assertBoolean($amenities['is_active'], false);
        $this->assertTimestampColumn($amenities['created_at'], false);
        $this->assertTimestampColumn($amenities['updated_at'], true);

        $roomAmenities = $this->columns('room_amenities');
        self::assertSame(['room_id', 'amenity_id'], array_keys($roomAmenities));
        $this->assertBigIntUnsigned($roomAmenities['room_id'], false, false);
        $this->assertBigIntUnsigned($roomAmenities['amenity_id'], false, false);

        foreach (['room_types', 'rooms', 'room_images', 'amenities', 'room_amenities'] as $table) {
            self::assertSame('InnoDB', $this->tableOption($table, 'ENGINE'));
            self::assertSame('utf8mb4_unicode_ci', $this->tableOption($table, 'TABLE_COLLATION'));
        }

        self::assertSame([
            'PRIMARY' => 'PRIMARY KEY',
            'chk_room_types_base_price' => 'CHECK',
            'chk_room_types_capacity_adults' => 'CHECK',
            'chk_room_types_capacity_children' => 'CHECK',
            'chk_room_types_is_active' => 'CHECK',
            'uq_room_types_slug' => 'UNIQUE',
        ], $this->constraints('room_types'));
        self::assertSame([
            'PRIMARY' => 'PRIMARY KEY',
            'chk_rooms_capacity_adults' => 'CHECK',
            'chk_rooms_capacity_children' => 'CHECK',
            'chk_rooms_is_published' => 'CHECK',
            'chk_rooms_operational_status' => 'CHECK',
            'chk_rooms_price_per_night' => 'CHECK',
            'fk_rooms_room_type' => 'FOREIGN KEY',
            'uq_rooms_room_number' => 'UNIQUE',
            'uq_rooms_slug' => 'UNIQUE',
        ], $this->constraints('rooms'));
        self::assertSame([
            'PRIMARY' => 'PRIMARY KEY',
            'chk_room_images_file_path' => 'CHECK',
            'chk_room_images_is_primary' => 'CHECK',
            'chk_room_images_sort_order' => 'CHECK',
            'fk_room_images_room' => 'FOREIGN KEY',
        ], $this->constraints('room_images'));
        self::assertSame([
            'PRIMARY' => 'PRIMARY KEY',
            'chk_amenities_is_active' => 'CHECK',
            'uq_amenities_name' => 'UNIQUE',
        ], $this->constraints('amenities'));
        self::assertSame([
            'PRIMARY' => 'PRIMARY KEY',
            'fk_room_amenities_amenity' => 'FOREIGN KEY',
            'fk_room_amenities_room' => 'FOREIGN KEY',
        ], $this->constraints('room_amenities'));

        self::assertSame(['RESTRICT', 'RESTRICT'], $this->referentialRules('fk_rooms_room_type'));
        self::assertSame(['RESTRICT', 'CASCADE'], $this->referentialRules('fk_room_images_room'));
        self::assertSame(['RESTRICT', 'CASCADE'], $this->referentialRules('fk_room_amenities_room'));
        self::assertSame(['RESTRICT', 'CASCADE'], $this->referentialRules('fk_room_amenities_amenity'));

        $statusCheck = $this->checkClause('rooms', 'chk_rooms_operational_status');
        foreach (['operational_status', 'active', 'maintenance', 'inactive'] as $requiredValue) {
            self::assertStringContainsString($requiredValue, $statusCheck);
        }

        self::assertSame(
            count(self::APPLICATION_MIGRATIONS),
            (int) $this->connection->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn(),
        );
    }

    public function testRoomDatabaseRulesAndReferentialActionsAreEnforced(): void
    {
        $this->runner->migrate();
        $roomTypeId = $this->insertRoomType('Deluxe Double', 'deluxe-double', '6500.00', 2, 1, 1);
        $roomId = $this->insertRoom($roomTypeId, '101', 'Garden Room 101', 'garden-room-101', '7000.00', 2, 1, 'active', 1);
        $imageId = $this->insertRoomImage($roomId, 'rooms/101/primary.webp', 0, 1);
        $amenityId = $this->insertAmenity('Wi-Fi', 'wifi', 1);
        $this->insertRoomAmenity($roomId, $amenityId);

        self::assertGreaterThan(0, $roomTypeId);
        self::assertGreaterThan(0, $roomId);
        self::assertGreaterThan(0, $imageId);
        self::assertGreaterThan(0, $amenityId);

        $storedRoom = $this->connection->query(
            "SELECT description, deleted_at, created_at, updated_at FROM rooms WHERE id = {$roomId}",
        )->fetch();
        self::assertIsArray($storedRoom);
        self::assertNull($storedRoom['description']);
        self::assertNull($storedRoom['deleted_at']);
        self::assertIsString($storedRoom['created_at']);
        self::assertIsString($storedRoom['updated_at']);

        $this->assertPdoFailure(fn () => $this->insertRoomType('Duplicate Slug', 'DELUXE-DOUBLE', '1000.00', 1, 0, 1));
        $this->assertPdoFailure(fn () => $this->insertRoomType('Zero Price', 'zero-price', '0.00', 1, 0, 1));
        $this->assertPdoFailure(fn () => $this->insertRoomType('No Adults', 'no-adults', '1000.00', 0, 0, 1));
        $this->assertPdoFailure(fn () => $this->insertRoomType('Negative Children', 'negative-children', '1000.00', 1, -1, 1));
        $this->assertPdoFailure(fn () => $this->insertRoomType('Bad Active', 'bad-active', '1000.00', 1, 0, 2));

        $this->assertPdoFailure(fn () => $this->insertRoom($roomTypeId, '101', 'Duplicate Number', 'duplicate-number', '1000.00', 1, 0, 'active', 1));
        $this->assertPdoFailure(fn () => $this->insertRoom($roomTypeId, '102', 'Duplicate Slug', 'GARDEN-ROOM-101', '1000.00', 1, 0, 'active', 1));
        $this->assertPdoFailure(fn () => $this->insertRoom($roomTypeId, '103', 'Zero Price', 'zero-room-price', '0.00', 1, 0, 'active', 1));
        $this->assertPdoFailure(fn () => $this->insertRoom($roomTypeId, '104', 'No Adults', 'no-room-adults', '1000.00', 0, 0, 'active', 1));
        $this->assertPdoFailure(fn () => $this->insertRoom($roomTypeId, '105', 'Negative Children', 'negative-room-children', '1000.00', 1, -1, 'active', 1));
        $this->assertPdoFailure(fn () => $this->insertRoom($roomTypeId, '106', 'Unknown Status', 'unknown-status', '1000.00', 1, 0, 'available', 1));

        foreach (['ACTIVE', 'Maintenance', 'INACTIVE'] as $index => $status) {
            $this->assertPdoFailure(fn () => $this->insertRoom(
                $roomTypeId,
                '20' . $index,
                'Case Status ' . $index,
                'case-status-' . $index,
                '1000.00',
                1,
                0,
                $status,
                1,
            ));
        }

        $this->assertPdoFailure(fn () => $this->insertRoom($roomTypeId, '107', 'Bad Published', 'bad-published', '1000.00', 1, 0, 'active', 2));
        $this->assertPdoFailure(fn () => $this->insertRoom($roomTypeId + 999, '108', 'Missing Type', 'missing-type', '1000.00', 1, 0, 'active', 1));
        $this->assertPdoFailure(fn () => $this->deleteById('room_types', $roomTypeId));
        $this->assertPdoFailure(fn () => $this->updateId('room_types', $roomTypeId, $roomTypeId + 100));

        foreach (['', '/absolute.webp', '../escape.webp', 'rooms/../escape.webp', 'rooms\\escape.webp', 'rooms//escape.webp', './escape.webp'] as $index => $path) {
            $this->assertPdoFailure(fn () => $this->insertRoomImage($roomId, $path, $index + 1, 0));
        }

        $this->assertPdoFailure(fn () => $this->insertRoomImage($roomId, 'rooms/negative.webp', -1, 0));
        $this->assertPdoFailure(fn () => $this->insertRoomImage($roomId, 'rooms/bad-primary.webp', 1, 2));
        $this->assertPdoFailure(fn () => $this->insertRoomImage($roomId + 999, 'rooms/missing.webp', 1, 0));

        $this->assertPdoFailure(fn () => $this->insertAmenity('WI-FI', null, 1));
        $this->assertPdoFailure(fn () => $this->insertAmenity('Bad Active Amenity', null, 2));
        $this->assertPdoFailure(fn () => $this->insertRoomAmenity($roomId, $amenityId));
        $this->assertPdoFailure(fn () => $this->insertRoomAmenity($roomId + 999, $amenityId));
        $this->assertPdoFailure(fn () => $this->insertRoomAmenity($roomId, $amenityId + 999));

        $secondAmenityId = $this->insertAmenity('Air conditioning', 'snow', 1);
        $this->insertRoomAmenity($roomId, $secondAmenityId);
        $this->deleteById('amenities', $secondAmenityId);
        self::assertSame(0, (int) $this->connection->query(
            "SELECT COUNT(*) FROM room_amenities WHERE amenity_id = {$secondAmenityId}",
        )->fetchColumn());

        $this->deleteById('rooms', $roomId);
        self::assertSame(0, (int) $this->connection->query(
            "SELECT COUNT(*) FROM room_images WHERE room_id = {$roomId}",
        )->fetchColumn());
        self::assertSame(0, (int) $this->connection->query(
            "SELECT COUNT(*) FROM room_amenities WHERE room_id = {$roomId}",
        )->fetchColumn());

        $this->connection->exec("UPDATE room_types SET updated_at = '2000-01-01 00:00:00' WHERE id = {$roomTypeId}");
        $this->connection->exec("UPDATE room_types SET name = 'Updated Deluxe' WHERE id = {$roomTypeId}");
        self::assertNotSame(
            '2000-01-01 00:00:00',
            $this->connection->query("SELECT updated_at FROM room_types WHERE id = {$roomTypeId}")->fetchColumn(),
        );
    }

    public function testRoomMigrationsRollbackInDependencyOrderAndRemigrateCleanly(): void
    {
        self::assertSame(self::APPLICATION_MIGRATIONS, $this->runner->migrate());
        self::assertSame(5, $this->roomTableCount());

        self::assertSame(array_reverse(self::APPLICATION_MIGRATIONS), $this->runner->rollbackLastBatch());
        self::assertSame(0, $this->roomTableCount());
        self::assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn());

        self::assertSame(self::APPLICATION_MIGRATIONS, $this->runner->migrate());
        self::assertSame(5, $this->roomTableCount());
        self::assertSame(array_reverse(self::APPLICATION_MIGRATIONS), $this->runner->rollbackLastBatch());
        self::assertSame(0, $this->roomTableCount());
    }

    /** @return array<string, array<string, mixed>> */
    private function columns(string $table): array
    {
        $statement = $this->connection->prepare(
            'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, '
            . 'CHARACTER_MAXIMUM_LENGTH, COLLATION_NAME, NUMERIC_PRECISION, NUMERIC_SCALE '
            . 'FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name ORDER BY ORDINAL_POSITION',
        );
        $statement->execute(['table_name' => $table]);
        $columns = [];

        foreach ($statement->fetchAll() as $column) {
            self::assertIsArray($column);
            $columns[$column['COLUMN_NAME']] = $column;
        }

        return $columns;
    }

    /** @param array<string, mixed> $column */
    private function assertBigIntUnsigned(array $column, bool $nullable, bool $autoIncrement): void
    {
        self::assertSame('bigint', $column['DATA_TYPE']);
        self::assertStringContainsString('unsigned', $column['COLUMN_TYPE']);
        self::assertSame($nullable ? 'YES' : 'NO', $column['IS_NULLABLE']);
        self::assertSame($autoIncrement ? 'auto_increment' : '', strtolower((string) $column['EXTRA']));
    }

    /** @param array<string, mixed> $column */
    private function assertVarchar(array $column, int $length, bool $nullable): void
    {
        self::assertSame('varchar', $column['DATA_TYPE']);
        self::assertSame($length, (int) $column['CHARACTER_MAXIMUM_LENGTH']);
        self::assertSame($nullable ? 'YES' : 'NO', $column['IS_NULLABLE']);
        self::assertSame('utf8mb4_unicode_ci', $column['COLLATION_NAME']);
    }

    /** @param array<string, mixed> $column */
    private function assertText(array $column, bool $nullable): void
    {
        self::assertSame('text', $column['DATA_TYPE']);
        self::assertSame($nullable ? 'YES' : 'NO', $column['IS_NULLABLE']);
        self::assertSame('utf8mb4_unicode_ci', $column['COLLATION_NAME']);
    }

    /** @param array<string, mixed> $column */
    private function assertDecimal(array $column, int $precision, int $scale, bool $nullable): void
    {
        self::assertSame('decimal', $column['DATA_TYPE']);
        self::assertSame($precision, (int) $column['NUMERIC_PRECISION']);
        self::assertSame($scale, (int) $column['NUMERIC_SCALE']);
        self::assertSame($nullable ? 'YES' : 'NO', $column['IS_NULLABLE']);
    }

    /** @param array<string, mixed> $column */
    private function assertInteger(array $column, bool $nullable): void
    {
        self::assertSame('int', $column['DATA_TYPE']);
        self::assertSame($nullable ? 'YES' : 'NO', $column['IS_NULLABLE']);
    }

    /** @param array<string, mixed> $column */
    private function assertBoolean(array $column, bool $nullable): void
    {
        self::assertSame('tinyint', $column['DATA_TYPE']);
        self::assertSame('tinyint(1)', strtolower((string) $column['COLUMN_TYPE']));
        self::assertSame($nullable ? 'YES' : 'NO', $column['IS_NULLABLE']);
        self::assertNull($column['COLUMN_DEFAULT']);
    }

    /** @param array<string, mixed> $column */
    private function assertTimestampColumn(array $column, bool $onUpdate): void
    {
        self::assertSame('datetime', $column['DATA_TYPE']);
        self::assertSame('NO', $column['IS_NULLABLE']);
        self::assertStringContainsString('current_timestamp', strtolower((string) $column['COLUMN_DEFAULT']));

        if ($onUpdate) {
            self::assertStringContainsString('on update current_timestamp', strtolower((string) $column['EXTRA']));
        } else {
            self::assertStringNotContainsString('on update', strtolower((string) $column['EXTRA']));
        }
    }

    /** @param array<string, mixed> $column */
    private function assertNullableDateTime(array $column): void
    {
        self::assertSame('datetime', $column['DATA_TYPE']);
        self::assertSame('YES', $column['IS_NULLABLE']);
        self::assertSame('NULL', $column['COLUMN_DEFAULT']);
    }

    private function tableOption(string $table, string $column): string
    {
        if (!in_array($column, ['ENGINE', 'TABLE_COLLATION'], true)) {
            self::fail('Unsafe information-schema column.');
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT %s FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name',
                $column,
            ),
        );
        $statement->execute(['table_name' => $table]);
        $value = $statement->fetchColumn();
        self::assertIsString($value);

        return $value;
    }

    /** @return array<string, string> */
    private function constraints(string $table): array
    {
        $statement = $this->connection->prepare(
            'SELECT CONSTRAINT_NAME, CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS '
            . 'WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :table_name ORDER BY CONSTRAINT_NAME',
        );
        $statement->execute(['table_name' => $table]);
        $constraints = [];

        foreach ($statement->fetchAll() as $constraint) {
            $constraints[$constraint['CONSTRAINT_NAME']] = $constraint['CONSTRAINT_TYPE'];
        }

        ksort($constraints, SORT_STRING);

        return $constraints;
    }

    /** @return array{0: string, 1: string} */
    private function referentialRules(string $constraint): array
    {
        $statement = $this->connection->prepare(
            'SELECT UPDATE_RULE, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS '
            . 'WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = :constraint_name',
        );
        $statement->execute(['constraint_name' => $constraint]);
        $row = $statement->fetch();
        self::assertIsArray($row);

        return [$row['UPDATE_RULE'], $row['DELETE_RULE']];
    }

    private function checkClause(string $table, string $constraint): string
    {
        $statement = $this->connection->prepare(
            'SELECT cc.CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS cc '
            . 'INNER JOIN information_schema.TABLE_CONSTRAINTS tc '
            . 'ON tc.CONSTRAINT_SCHEMA = cc.CONSTRAINT_SCHEMA AND tc.CONSTRAINT_NAME = cc.CONSTRAINT_NAME '
            . 'WHERE tc.CONSTRAINT_SCHEMA = DATABASE() AND tc.TABLE_NAME = :table_name '
            . 'AND tc.CONSTRAINT_NAME = :constraint_name',
        );
        $statement->execute(['table_name' => $table, 'constraint_name' => $constraint]);
        $value = $statement->fetchColumn();
        self::assertIsString($value);

        return $value;
    }

    private function insertRoomType(
        string $name,
        string $slug,
        string $price,
        int $adults,
        int $children,
        int $active,
    ): int {
        $statement = $this->connection->prepare(
            'INSERT INTO room_types '
            . '(name, slug, description, base_price, capacity_adults, capacity_children, is_active) '
            . 'VALUES (:name, :slug, NULL, :base_price, :adults, :children, :active)',
        );
        $statement->execute([
            'name' => $name,
            'slug' => $slug,
            'base_price' => $price,
            'adults' => $adults,
            'children' => $children,
            'active' => $active,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertRoom(
        int $roomTypeId,
        string $roomNumber,
        string $name,
        string $slug,
        string $price,
        int $adults,
        int $children,
        string $status,
        int $published,
    ): int {
        $statement = $this->connection->prepare(
            'INSERT INTO rooms '
            . '(room_type_id, room_number, name, slug, description, price_per_night, capacity_adults, '
            . 'capacity_children, operational_status, is_published, deleted_at) '
            . 'VALUES (:room_type_id, :room_number, :name, :slug, NULL, :price, :adults, :children, '
            . ':status, :published, NULL)',
        );
        $statement->execute([
            'room_type_id' => $roomTypeId,
            'room_number' => $roomNumber,
            'name' => $name,
            'slug' => $slug,
            'price' => $price,
            'adults' => $adults,
            'children' => $children,
            'status' => $status,
            'published' => $published,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertRoomImage(int $roomId, string $path, int $sortOrder, int $primary): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO room_images (room_id, file_path, alt_text, sort_order, is_primary) '
            . 'VALUES (:room_id, :file_path, NULL, :sort_order, :is_primary)',
        );
        $statement->execute([
            'room_id' => $roomId,
            'file_path' => $path,
            'sort_order' => $sortOrder,
            'is_primary' => $primary,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertAmenity(string $name, ?string $icon, int $active): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO amenities (name, icon, is_active) VALUES (:name, :icon, :active)',
        );
        $statement->execute(['name' => $name, 'icon' => $icon, 'active' => $active]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertRoomAmenity(int $roomId, int $amenityId): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO room_amenities (room_id, amenity_id) VALUES (:room_id, :amenity_id)',
        );
        $statement->execute(['room_id' => $roomId, 'amenity_id' => $amenityId]);
    }

    private function deleteById(string $table, int $id): void
    {
        if (!in_array($table, ['room_types', 'rooms', 'amenities'], true)) {
            self::fail('Unsafe test table identifier.');
        }

        $statement = $this->connection->prepare(sprintf('DELETE FROM `%s` WHERE id = :id', $table));
        $statement->execute(['id' => $id]);
    }

    private function updateId(string $table, int $id, int $newId): void
    {
        if ($table !== 'room_types') {
            self::fail('Unsafe test table identifier.');
        }

        $statement = $this->connection->prepare('UPDATE room_types SET id = :new_id WHERE id = :id');
        $statement->execute(['new_id' => $newId, 'id' => $id]);
    }

    private function assertPdoFailure(callable $operation): void
    {
        try {
            $operation();
            self::fail('MariaDB unexpectedly accepted invalid room data.');
        } catch (PDOException) {
            self::assertTrue(true);
        }
    }

    private function roomTableCount(): int
    {
        return (int) $this->connection->query(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() "
            . "AND TABLE_NAME IN ('room_types', 'rooms', 'room_images', 'amenities', 'room_amenities')",
        )->fetchColumn();
    }
}
