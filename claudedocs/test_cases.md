# Test Cases

Run all unit tests: `composer test:unit`
Run all suites: `composer test`
Integration tests require Docker: `docker-compose up -d`

---

## Unit Suite

### ServiceProviderTest

**Implemented**
- `test_consumer_interface_is_bound_in_container` — `ConsumerInterface` is registered in the container after boot
- `test_consumer_interface_is_mapped_to_rabbitmq_consumer` — service provider `$bindings` maps `ConsumerInterface` → `RabbitMQConsumer`
- `test_config_is_merged_from_package_defaults` — `config('common-resources.rabbitmq.exchange')` equals `'gifts'` after boot
- `test_external_queue_work_command_is_registered` — artisan command list contains `common-resources:queue-external:work` when running in console

---

### Queue/MessageTest

**Implemented**
- `test_json_round_trip_preserves_content_and_class` — `Message::fromJson($msg->toJson())` restores `content` and `class`
- `test_to_array_contains_content_and_class_keys` — `toArray()` returns array with `content` and `class` keys

---

### Queue/MultiQueueBindingTest

**Implemented**
- `test_bindings_for_named_queue_are_accessible_via_dot_notation` — `config('common-resources.bindings.{queue}')` returns the correct group
- `test_unknown_queue_name_returns_empty_array` — non-existent group returns the provided default
- `test_each_queue_only_receives_its_own_routing_keys` — event classes in one group do not appear in another

---

### Queue/Events/EventRoutingKeyTest

**Implemented**
- `test_routing_key_follows_name_dot_version_format` — `routingKey()` always equals `routingName() . '.v' . version()`
- `test_routing_keys_are_stable` *(× 7 via data provider)* — exact routing key for every event class in the package

---

### Queue/Events/EventSerializationTest

Subject: `ProductAddedToUpsellGroup`

**Implemented**
- `test_to_array_from_array_round_trip_preserves_constructor_fields` — `productId` and `upsellGroupId` survive the round-trip
- `test_to_array_from_array_round_trip_preserves_extra_properties` — `site`, `eventId`, `sendAt` survive the round-trip
- `test_to_array_contains_expected_top_level_keys` — output has `site`, `sendAt`, `data`, `resourceId`, `version`, `eventId`, `routingKey`
- `test_resource_id_matches_product_id` — `resourceId()` returns `productId`

---

### Queue/Events/ArrayableTraitTest

**Implemented**
- `test_from_array_uses_constructor_default_when_field_is_absent` — missing field falls back to the parameter default
- `test_from_array_deserializes_backed_enum_field` — string value is cast to the correct `BackedEnum` case (e.g. `LocationType`)
- `test_from_array_deserializes_uuid_field` — string UUID is restored as `UuidInterface`
- `test_from_array_handles_nullable_object_as_null` — `null` in array stays `null` for a nullable typed property
- `test_data_returns_only_constructor_parameters` — `data()` does not include `site`, `eventId`, `sendAt` from `ExtraPropertiesTrait`

---

### Queue/Events/ExtraPropertiesTraitTest

**Implemented**
- `test_set_event_id_generates_a_valid_uuid` — result of `setEventId()` satisfies `UuidInterface`
- `test_set_event_id_generates_unique_ids_on_each_call` — two calls produce different UUIDs
- `test_set_send_at_sets_a_carbon_immutable_close_to_now` — stored timestamp is within 1 second of `now()`
- `test_set_site_is_returned_by_site` — `setSite(Site::PL)` → `site()` returns `Site::PL`

---

### Queue/Events/Product/ProductUpdatedTest

**Implemented**
- `test_round_trip_preserves_required_fields` — required constructor fields survive `toArray()` → `fromArray()`
- `test_round_trip_preserves_optional_fields_with_defaults` — optional fields (`packageChildrenIds`, `isGlobal`, etc.) use and restore defaults
- `test_resource_id_returns_id` — `resourceId()` returns `$id`
- `test_resource_uuid_returns_null` — `resourceUuid()` returns `null`

---

### Queue/Events/Order/OrderPaidTest

**Implemented**
- `test_round_trip_preserves_id_and_site` — `id` and `site` survive the round-trip
- `test_constructor_site_does_not_conflict_with_extra_properties_site` — after `setSite(Site::EE)`, `site()` returns `Site::EE`; the constructor `$site` field remains independent
- `test_resource_id_returns_id` — `resourceId()` returns `$id`

---

### Queue/Events/Location/LocationUpdatedTest

**Implemented**
- `test_round_trip_preserves_uuid_id_field` — `UuidInterface $id` serializes as string and restores as `UuidInterface`
- `test_round_trip_preserves_location_type_enum` — `LocationType` enum field deserializes correctly
- `test_round_trip_with_null_geometry` — nullable `?GeoJsonGeometryDTO` round-trips as `null`
- `test_resource_uuid_returns_id` — `resourceUuid()` returns the `$id` field
- `test_resource_id_returns_null` — `resourceId()` returns `null`

---

### Queue/Events/Cache/CloudflareCachePurgeRequestedTest

**Implemented**
- `test_round_trip_preserves_tags_array` — array of strings survives round-trip
- `test_resource_id_and_uuid_return_null` — both `resourceId()` and `resourceUuid()` return `null`

---

### Queue/Events/System/ExternalQueueRestartRequestedTest

**Implemented**
- `test_round_trip_with_default_reason` — empty default `reason` survives round-trip
- `test_round_trip_with_custom_reason` — custom reason string survives round-trip

---

### Enums/SiteTest

**Implemented**
- `test_from_long_name_underscore_code_maps_all_cases` — each known long underscore code resolves to the correct `Site` case
- `test_from_long_name_dot_code_maps_all_cases` — each known long dot code resolves to the correct `Site` case
- `test_from_short_name_underscore_code_maps_all_cases` — each known short code resolves to the correct `Site` case
- `test_unknown_code_throws_invalid_argument_exception` — unknown string throws `InvalidArgumentException`

---

### Enums/LangTest

**Implemented**
- `test_from_site_returns_correct_langs_for_each_site` — each `Site` case maps to its expected `Lang` cases
- `test_values_returns_all_raw_values` — `Lang::values()` contains all expected string values

---

### Enums/ArrayableEnumTraitTest

**Implemented**
- `test_values_returns_all_raw_values` — `values()` returns raw string/int values of all cases
- `test_names_returns_all_case_names` — `names()` returns the PHP case name strings
- `test_array_returns_value_to_name_map` — `array()` returns `['pl' => 'PL', ...]` style map

---

### ModelCasts/UuidModelCastTest

**Implemented**
- `test_get_converts_string_to_uuid_interface` — string UUID is cast to `UuidInterface`
- `test_get_returns_uuid_interface_unchanged` — passing an existing `UuidInterface` returns it as-is
- `test_get_returns_null_for_null_value` — null value returns null
- `test_set_converts_uuid_interface_to_string` — `UuidInterface` is stored as a plain string in the `[$key => $value]` array
- `test_set_stores_plain_string_unchanged` — string value passes through to the result array

---

### Support/SingletonTraitTest

**Implemented**
- `test_get_instance_returns_same_object_on_repeated_calls` — two calls to `getInstance()` return the identical instance
- `test_destroy_allows_fresh_instance_to_be_created` — after `destroy()`, `getInstance()` returns a new instance
- `test_clone_throws_logic_exception` — trying to clone the singleton throws `LogicException`
- `test_wakeup_throws_logic_exception` — unserializing throws `LogicException`

---

### Support/Config/VanillaPHPConfigTest

**Implemented**
- `test_get_returns_default_when_config_file_does_not_exist` — missing file path returns the provided default
- `test_get_reads_nested_key_with_dot_notation` — a key like `rabbitmq.host` resolves through nested arrays
- `test_get_returns_default_for_missing_key` — existing config file but absent key returns default

---

### Rules/GeoJsonRuleTest

**Implemented**
- `test_valid_point_passes` — `["type" => "Point", "coordinates" => [21.0, 52.0]]` passes
- `test_point_out_of_range_fails` — lon > 180 or lat > 90 triggers a failure
- `test_valid_polygon_passes` — a properly closed linear ring passes
- `test_polygon_with_holes_fails` — polygon with more than one ring is rejected
- `test_polygon_self_intersection_fails` — self-intersecting ring triggers failure
- `test_self_intersection_allowed_when_disabled` — with `validateIntersection = false`, self-intersecting ring passes
- `test_valid_multipolygon_passes` — two non-overlapping polygons pass
- `test_intersecting_multipolygon_fails` — two overlapping polygons fail
- `test_line_string_with_one_position_fails` — fewer than two positions fails
- `test_null_input_fails` — null value triggers failure message
- `test_missing_type_key_fails` — array without `type` key fails
- `test_unknown_geometry_type_fails` — unrecognised type string fails
- `test_geometry_collection_fails_with_not_implemented_message` — GeometryCollection type fails

---

### Rules/MultiGeoJsonRuleTest

**Implemented**
- `test_array_of_points_is_validated_as_multipoint` — list of point geometries is complexified and validated
- `test_array_of_polygons_is_validated_as_multipolygon` — list of polygon geometries is complexified and validated
- `test_single_geometry_passes_unchanged` — single-element array passes through without complexifying
- `test_null_input_fails` — null fails with expected message
- `test_non_array_with_type_key_fails` — passing a single geometry object (not wrapped in array) fails
- `test_missing_type_in_geometry_fails` — element without `type` key triggers failure

---

### Services/LocationsHelperTest

**Implemented**
- `test_invert_coords_swaps_lon_lat_for_point` — `[lat, lon]` input becomes `[lon, lat]`
- `test_invert_coords_swaps_all_positions_in_polygon` — every position in a polygon ring is swapped
- `test_convert_coords_to_float_casts_string_values` — string coordinate values are cast to float
- `test_convert_coords_to_float_rounds_to_six_decimal_places` — values are rounded to 6 decimals
- `test_close_geometry_type_appends_first_point_to_open_polygon_ring` — open ring gets first position appended as closing point
- `test_close_geometry_type_leaves_closed_polygon_unchanged` — already-closed ring is not modified
- `test_complexify_geo_json_types_list_of_points_to_multipoint` — list of `Point` DTOs produces a `MultiPoint`
- `test_complexify_geo_json_types_list_of_polygons_to_multipolygon` — list of `Polygon` DTOs produces a `MultiPolygon`
- `test_complexify_geo_json_types_empty_array_throws_invalid_argument_exception` — empty array throws `InvalidArgumentException`
- `test_simplify_geo_json_types_multipolygon_splits_into_polygons` — `MultiPolygon` returns list of individual `Polygon` DTOs
- `test_circle_to_polygon_returns_closed_polygon_dto` — output is a `GeoJsonGeometryDTO` of type `Polygon` with a closed ring
- `test_geometry_collection_throws_not_implemented` — passing `GEOMETRY_COLLECTION` throws `NotImplemented`

---

### Enums/PromotionTypeTest

**Implemented**
- `test_priority_returns_correct_order` — `MULTI_DISCOUNT` = 1, `FREE_GIFT` = 2, `LIMITED_QUANTITY` = 3
- `test_is_blocked_by_discount_codes` — `MULTI_DISCOUNT` and `FREE_GIFT` return true; `LIMITED_QUANTITY` returns false
- `test_mutually_exclusive_types` — `MULTI_DISCOUNT` excludes `FREE_GIFT` and vice versa; `LIMITED_QUANTITY` excludes nothing

---

### Enums/CloudflareCacheTagTest

**Implemented**
- `test_build_site_tag_returns_site_prefixed_value` — `SITE->build(Site::PL)` returns `"site:pl"`
- `test_build_tagged_cache_key_with_value` — `PRODUCT_ID->build(Site::PL, 42)` returns `"pl_pro:42"`
- `test_build_without_value_throws_for_non_site_types` — calling `build(Site::PL, null)` on a non-SITE case throws `InvalidArgumentException`

---

## Integration Suite

### Queue/RabbitMQSetupperTest *(not yet created)*

**TODO**
- `test_setup_creates_queue_with_correct_name` — declared queue matches `{env}.{project}.{queueName}`
- `test_setup_creates_dead_letter_queue` — a `{queue}_dlq` queue is created alongside the main queue
- `test_setup_binds_routing_keys_for_named_group` — only routing keys from the named bindings group are bound
- `test_setup_unbinds_removed_routing_keys` — keys absent from config but present in the bindings file are unbound
- `test_setup_writes_per_queue_bindings_file` — `rabbitmq_bindings_{queueName}.json` is written with current keys
- `test_different_queue_names_create_separate_queues` — two `setup()` calls with different names produce two independent queues

---

### Queue/RabbitMQPublisherTest *(not yet created)*

**TODO**
- `test_publish_sends_message_to_exchange_without_error` — publishing an event does not throw
- `test_publish_retries_after_connection_drop` — after a forced disconnect, `publish()` reconnects and succeeds

---

### Queue/RabbitMQConsumerTest *(not yet created)*

**TODO**
- `test_listener_is_called_for_event_in_correct_queue` — publishing triggers the listener registered under the matching queue name
- `test_listener_is_not_called_for_different_queue` — event does not trigger a listener registered under a different queue name
- `test_failed_listener_sends_message_to_dlq_after_retries` — after 3 retry attempts message ends up in the dead-letter queue
- `test_external_queue_restart_requested_causes_consumer_to_exit` — receiving `ExternalQueueRestartRequested` causes the consumer to exit
