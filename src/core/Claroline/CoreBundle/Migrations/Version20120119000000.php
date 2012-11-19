<?php

namespace Claroline\CoreBundle\Migrations;

use Claroline\CoreBundle\Library\Installation\BundleMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20120119000000 extends BundleMigration
{
    public function up(Schema $schema)
    {
        $this->createIconTypeTable($schema);
        $this->createResourceIconTable($schema);
        $this->createMetaTypeTable($schema);
        $this->createLicenseTable($schema);
        $this->createWorkspaceTable($schema);
        $this->createUserTable($schema);
        $this->createGroupTable($schema);
        $this->createUserGroupTable($schema);
        $this->createWorkspaceAggregationTable($schema);
        $this->createRoleTable($schema);
        $this->createUserRoleTable($schema);
        $this->createGroupRoleTable($schema);
        $this->createPluginTable($schema);
        $this->createResourceTypeTable($schema);
        $this->createResourceTable($schema);
        $this->createDirectoryTable($schema);
        $this->createFileTable($schema);
        $this->createTextContentTable($schema);
        $this->createTextTable($schema);
        $this->createMessageTable($schema);
        $this->createResourceInstanceTable($schema);
        $this->createMetaTypeResourceTypeTable($schema);
        $this->createLinkTable($schema);
        $this->createResourceTypeCustomActionsTable($schema);
        $this->createResourceLoggerTable($schema);
        $this->createWidgetTable($schema);
        $this->createAdminWidgetConfig($schema);

    }

    public function down(Schema $schema)
    {
        $schema->dropTable('claro_link');
        $schema->dropTable('claro_file');
        $schema->dropTable('claro_text_content');
        $schema->dropTable('claro_text');
        $schema->dropTable('claro_directory');
        $schema->dropTable('claro_resource');
        $schema->dropTable('claro_resource_type');
        $schema->dropTable('claro_resource_icon');
        $schema->dropTable('claro_resource_icon_type');
        $schema->dropTable('claro_extension');
        $schema->dropTable('claro_tool_instance');
        $schema->dropTable('claro_tool');
        $schema->dropTable('claro_plugin');
        $schema->dropTable('claro_group_role');
        $schema->dropTable('claro_user_role');
        $schema->dropTable('claro_role');
        $schema->dropTable('claro_workspace_aggregation');
        $schema->dropTable('claro_workspace');
        $schema->dropTable('claro_group');
        $schema->dropTable('claro_user');
        $schema->dropTable('claro_workspace_message');
        $schema->dropTable('claro_resource_instance');
        $schema->dropTable('claro_license');
        $schema->dropTable('claro_meta_type');
        $schema->dropTable('claro_meta_type_resource_type');
        $schema->dropTable('claro_resource_type_custom_action');
        $schema->dropTable('claro_resource_logger');
        $schema->dropTable('claro_widget');
        $schema->dropTable('claro_widget_dispay');
    }

    private function createUserTable(Schema $schema)
    {
        $table = $schema->createTable('claro_user');

        $this->addId($table);
        $table->addColumn('first_name', 'string', array('length' => 50));
        $table->addColumn('last_name', 'string', array('length' => 50));
        $table->addColumn('username', 'string', array('length' => 255));
        $table->addColumn('password', 'string', array('length' => 255));
        $table->addColumn('salt', 'string', array('length' => 255));
        $table->addColumn('phone', 'string', array('notnull' => false));
        $table->addColumn('note', 'string', array('length' => 1000, 'notnull' => false));
        $table->addColumn('mail', 'string', array('length' => 255, 'notnull' => false));
        $table->addColumn('administrative_code', 'string', array('length' => 255, 'notnull' => false));
        $table->addColumn('workspace_id', 'integer', array('notnull' => false));
        $table->addColumn('creation_date', 'datetime');
        $table->addUniqueIndex(array('username'));

        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_workspace'), array('workspace_id'), array('id'), array('onDelete' => 'CASCADE')
        );
        $this->storeTable($table);
    }

    private function createGroupTable(Schema $schema)
    {
        $table = $schema->createTable('claro_group');

        $this->addId($table);
        $table->addColumn('name', 'string', array('length' => 255));
        $table->addUniqueIndex(array('name'));

        $this->storeTable($table);
    }

    private function createUserGroupTable(Schema $schema)
    {
        $table = $schema->createTable('claro_user_group');

        $table->addColumn('user_id', 'integer', array('notnull' => true));
        $table->addColumn('group_id', 'integer', array('notnull' => true));
        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_user'), array('user_id'), array('id'), array('onDelete' => 'CASCADE')
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('claro_group'), array('group_id'), array('id'), array('onDelete' => 'CASCADE')
        );

        //not working (yet)
        $table->addUniqueIndex(array('user_id', 'group_id'));
    }

    private function createWorkspaceTable(Schema $schema)
    {
        $table = $schema->createTable('claro_workspace');

        $this->addId($table);
        $this->addDiscriminator($table);
        $table->addColumn('name', 'string', array('length' => 255));
        $table->addColumn('is_public', 'boolean', array('notnull' => false));
        $table->addColumn('lft', 'integer', array('notnull' => false));
        $table->addColumn('rgt', 'integer', array('notnull' => false));
        $table->addColumn('lvl', 'integer', array('notnull' => false));
        $table->addColumn('root', 'integer', array('notnull' => false));
        $table->addColumn('parent_id', 'integer', array('notnull' => false));
        $table->addColumn('type', 'integer', array('notnull' => false));
        $table->addColumn('code', 'string', array('length' => 255));

        $this->storeTable($table);
    }

    private function createWorkspaceAggregationTable(Schema $schema)
    {
        $table = $schema->createTable('claro_workspace_aggregation');

        $table->addColumn('aggregator_workspace_id', 'integer', array('notnull' => true));
        $table->addColumn('workspace_id', 'integer', array('notnull' => true));
        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_workspace'), array('aggregator_workspace_id'), array('id'), array('onDelete' => 'CASCADE')
        );
        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_workspace'), array('workspace_id'), array('id'), array('onDelete' => 'CASCADE')
        );
    }

    private function createRoleTable(Schema $schema)
    {
        $table = $schema->createTable('claro_role');

        $this->addId($table);
        $this->addDiscriminator($table);
        $table->addColumn('name', 'string', array('length' => 255));
        $table->addColumn('translation_key', 'string', array('length' => 255, 'notnull' => false));
        $table->addColumn('is_read_only', 'boolean', array('notnull' => true));
        $table->addColumn('workspace_id', 'integer', array('notnull' => false));
        $table->addColumn('lft', 'integer', array('notnull' => true));
        $table->addColumn('rgt', 'integer', array('notnull' => true));
        $table->addColumn('lvl', 'integer', array('notnull' => true));
        $table->addColumn('root', 'integer', array('notnull' => false));
        $table->addColumn('parent_id', 'integer', array('notnull' => false));
        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_workspace'), array('workspace_id'), array('id'), array('onDelete' => 'CASCADE')
        );
        $table->addUniqueIndex(array('name'));

        $this->storeTable($table);
    }

    private function createUserRoleTable(Schema $schema)
    {
        $table = $schema->createTable('claro_user_role');
        $table->addColumn('user_id', 'integer', array('notnull' => true));
        $table->addColumn('role_id', 'integer', array('notnull' => true));
        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_user'), array('user_id'), array('id'), array('onDelete' => 'CASCADE')
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('claro_role'), array('role_id'), array('id'), array('onDelete' => 'CASCADE')
        );
    }

    private function createGroupRoleTable(Schema $schema)
    {
        $table = $schema->createTable('claro_group_role');

        $table->addColumn('group_id', 'integer', array('notnull' => true));
        $table->addColumn('role_id', 'integer', array('notnull' => true));
        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_group'), array('group_id'), array('id'), array('onDelete' => 'CASCADE')
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('claro_role'), array('role_id'), array('id'), array('onDelete' => 'CASCADE')
        );
    }

    private function createResourceTypeTable(Schema $schema)
    {
        $table = $schema->createTable('claro_resource_type');
        $this->addId($table);
        $table->addColumn('name', 'string');
        $table->addColumn('is_visible', 'boolean');
        $table->addColumn('is_browsable', 'boolean');
        $table->addColumn('plugin_id', 'integer', array('notnull' => false));
        $table->addColumn('class', 'string', array('notnull' => false));
        $table->addColumn('parent_id', 'integer', array('notnull' => false));
        $table->addUniqueIndex(array('name'));
        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_plugin'), array('plugin_id'), array('id'), array('onDelete' => 'CASCADE')
        );
        $this->storeTable($table);
    }

    public function createResourceTypeCustomActionsTable(Schema $schema)
    {
        $table = $schema->createTable('claro_resource_type_custom_action');

        $this->addId($table);
        $table->addColumn('action', 'string', array('notnull' => false));
        $table->addColumn('async', 'boolean', array('notnull' => false));
        $table->addColumn('resource_type_id', 'integer', array('notnull' => false));

        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_resource_type'), array('resource_type_id'), array('id'), array('onDelete' => 'SET NULL')
        );
    }

    private function createResourceTable(Schema $schema)
    {
        $table = $schema->createTable('claro_resource');

        $this->addId($table);
        $table->addColumn('license_id', 'integer', array('notnull' => false));
        $table->addColumn('share_type', 'integer', array('notnull' => false));
        $table->addColumn('created', 'datetime');
        $table->addColumn('updated', 'datetime');
        $table->addColumn('resource_type_id', 'integer', array('notnull' => false));
        $table->addColumn('user_id', 'integer', array('notnull' => false));
        $table->addColumn('icon_id', 'integer', array('notnull' => true));
        $table->addColumn('path', 'string', array('length' => 1000, 'notnull' => false));
        $table->addColumn('name', 'string');
        $table->addColumn('parent_id', 'integer', array('notnull' => false));
        $table->addColumn('lvl', 'integer', array('notnull' => false));
        $table->addColumn('workspace_id', 'integer');

        $this->addDiscriminator($table);

        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_license'), array('license_id'), array('id'), array("onDelete" => "CASCADE")
        );
        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_resource_type'), array('resource_type_id'), array('id'), array('onDelete' => 'SET NULL')
        );
        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_user'), array('user_id'), array('id'), array('onDelete' => 'CASCADE')
        );
        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_resource_icon'), array('icon_id'), array('id'), array('onDelete' => 'CASCADE')
        );

        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_workspace'), array('workspace_id'), array('id'), array('onDelete' => 'CASCADE')
        );

        $this->storeTable($table);
    }

    private function createPluginTable(Schema $schema)
    {
        $table = $schema->createTable('claro_plugin');

        $this->addId($table);
        $table->addColumn('vendor_name', 'string', array('length' => 50));
        $table->addColumn('short_name', 'string', array('length' => 50));
        $table->addColumn('has_options', 'boolean');
        $table->addColumn('icon', 'string', array('length' => 255));
        $table->addUniqueIndex(array('vendor_name', 'short_name'));
        $this->storeTable($table);
    }

    private function createFileTable(Schema $schema)
    {
        $table = $schema->createTable('claro_file');
        $this->addId($table);
        $table->addColumn('size', 'integer', array('notnull' => true));
        $table->addColumn('hash_name', 'string', array('length' => 50));
        $table->addColumn('mime_type', 'string', array('length' => 100));
        $table->addUniqueIndex(array('hash_name'));
        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_resource'), array('id'), array('id'), array('onDelete' => 'CASCADE')
        );
    }

    private function createDirectoryTable(Schema $schema)
    {
        $table = $schema->createTable('claro_directory');
        $this->addId($table);
        $this->storeTable($table);
        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_resource'), array('id'), array('id'), array('onDelete' => 'CASCADE')
        );
    }

    private function createMessageTable(Schema $schema)
    {
        $table = $schema->createTable('claro_workspace_message');
        $this->addId($table);
        $table->addColumn('workspace_id', 'integer');
        $table->addColumn('user_id', 'integer');
        $table->addColumn('content', 'text');
        $table->addColumn('date_creation', 'datetime');
        $table->addColumn('date_modification', 'datetime');
        $table->addColumn('last_modif_user_id', 'integer');
        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_workspace'), array('workspace_id'), array('id'), array('onDelete' => 'CASCADE')
        );
        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_user'), array('user_id'), array('id'), array('onDelete' => 'CASCADE')
        );
    }

    private function createResourceInstanceTable(Schema $schema)
    {
        $table = $schema->createTable('claro_resource_instance');
        $this->addId($table);
        $table->addColumn('path', 'string', array('length' => 1000, 'notnull' => false));
        $table->addColumn('name', 'string');
        $table->addColumn('parent_id', 'integer', array('notnull' => false));
        $table->addColumn('lvl', 'integer', array('notnull' => false));
        $table->addColumn('resource_id', 'integer');
        $table->addColumn('workspace_id', 'integer');
        $table->addColumn('user_id', 'integer', array('notnull' => true));
        $table->addColumn('created', 'datetime');
        $table->addColumn('updated', 'datetime');

        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_workspace'), array('workspace_id'), array('id'), array('onDelete' => 'CASCADE')
        );
        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_user'), array('user_id'), array('id'), array('onDelete' => 'CASCADE')
        );
        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_resource'), array('resource_id'), array('id'), array('onDelete' => 'CASCADE')
        );

        $table->addIndex(array('path'));
        $table->addUniqueIndex(array('parent_id', 'name'));
        $this->storeTable($table);
    }

    private function createLicenseTable(Schema $schema)
    {
        $table = $schema->createTable('claro_license');
        $this->addId($table);
        $table->addColumn('name', 'string', array('notnull' => true));
        $table->addColumn('acronym', 'string', array('notnull' => false));

        $this->storeTable($table);
    }

    private function createMetaTypeTable(Schema $schema)
    {
        $table = $schema->createTable('claro_meta_type');
        $this->addId($table);
        $table->addColumn('name', 'string', array('notnull' => true));

        $this->storeTable($table);
    }

    private function createMetaTypeResourceTypeTable(Schema $schema)
    {
        $table = $schema->createTable('claro_meta_type_resource_type');
        $this->addId($table);
        $table->addColumn('meta_type_id', 'integer', array('notnull' => true));
        $table->addColumn('resource_type_id', 'integer', array('notnull' => true));

        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_meta_type'), array('meta_type_id'), array('id'), array('onDelete' => 'CASCADE')
        );
        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_resource_type'), array('resource_type_id'), array('id'), array('onDelete' => 'CASCADE')
        );
    }

    private function createLinkTable(Schema $schema)
    {
        $table = $schema->createTable('claro_link');
        $this->addId($table);
        $table->addColumn('url', 'string');
    }

    private function createTextTable(Schema $schema)
    {
        $table = $schema->createTable('claro_text');
        $this->addId($table);
        $table->addColumn('version', 'integer');
        $table->addColumn('current_text_id', 'integer');

        $this->storeTable($table);
    }

    private function createTextContentTable(Schema $schema)
    {
        $table = $schema->createTable('claro_text_revision');
        $this->addId($table);
        $table->addColumn('content', 'text');
        $table->addColumn('version', 'integer');
        $table->addColumn('text_id', 'integer', array('notnull' => false));
        $table->addColumn('user_id', 'integer', array('notnull' => false));

        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_user'), array('user_id'), array('id'), array('onDelete' => 'SET NULL')
        );

        $this->storeTable($table);
    }

    private function createResourceIconTable(Schema $schema)
    {
        $table = $schema->createTable('claro_resource_icon');
        $this->addId($table);
        $table->addColumn('icon_location', 'string', array('notnull' => false, 'length' => 255));
        $table->addColumn('icon_type_id', 'integer', array('notnull' => false));
        $table->addColumn('type', 'string', array('length' => 255));

        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_resource_icon_type'), array('icon_type_id'), array('id'), array('onDelete' => 'SET NULL')
        );

        $this->storeTable($table);
    }

    private function createIconTypeTable(Schema $schema)
    {
        $table = $schema->createTable('claro_resource_icon_type');
        $this->addId($table);
        $table->addColumn('icon_type', 'text');

        $this->storeTable($table);
    }

    private function createResourceLoggerTable(Schema $schema)
    {
        $table = $schema->createTable('claro_resource_logger');
        $this->addId($table);
        $table->addColumn('resource_id', 'integer', array('notnull' => false));
        $table->addColumn('path', 'string');
        $table->addColumn('resource_type_id', 'integer');
        $table->addColumn('workspace_id', 'integer');
        $table->addColumn('creator_id', 'integer', array('notnull' => false));
        $table->addColumn('updator_id', 'integer', array('notnull' => false));
        $table->addColumn('url', 'string', array('notnull' => false));
        $table->addColumn('action', 'string');
        $table->addColumn('log_descr', 'string', array('notnull' => false));
        $table->addColumn('date_log', 'datetime');

        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_user'), array('creator_id'), array('id')
        );
        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_user'), array('updator_id'), array('id')
        );

        $this->storeTable($table);
    }

    private function createWidgetTable(Schema $schema)
    {
        $table = $schema->createTable('claro_widget');
        $this->addId($table);
        $table->addColumn('name', 'string');
        $table->addColumn('plugin_id', 'integer', array('notnull' => false));
        $table->addColumn('is_configurable', 'boolean');
        $table->addColumn('icon', 'string', array('length' => 255));
        $table->addUniqueIndex(array('name'));

        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_plugin'), array('plugin_id'), array('id'), array('onDelete' => 'CASCADE')
        );

         $this->storeTable($table);
    }

    private function createAdminWidgetConfig(Schema $schema)
    {
        $table = $schema->createTable('claro_widget_dispay');
        $this->addId($table);
        $table->addColumn('user_id', 'integer', array('notnull' => false));
        $table->addColumn('widget_id', 'integer');
        $table->addColumn('workspace_id', 'integer', array('notnull' => false));
        $table->addColumn('is_locked', 'boolean', array('notnull' => false));
        $table->addColumn('is_visible', 'boolean');
        $table->addColumn('is_desktop', 'boolean');
        $table->addColumn('lft', 'integer', array('notnull' => true));
        $table->addColumn('rgt', 'integer', array('notnull' => true));
        $table->addColumn('lvl', 'integer', array('notnull' => true));
        $table->addColumn('root', 'integer', array('notnull' => false));
        $table->addColumn('parent_id', 'integer', array('notnull' => false));

        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_user'), array('user_id'), array('id')
        );
        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_workspace'), array('workspace_id'), array('id')
        );
        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_widget'), array('widget_id'), array('id'), array('onDelete' => 'CASCADE')
        );
    }
}