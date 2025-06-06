<?php

namespace Archetype\Vendor\Doctrine\DBAL\Schema\Visitor;

use Archetype\Vendor\Doctrine\DBAL\Schema\Column;
use Archetype\Vendor\Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Archetype\Vendor\Doctrine\DBAL\Schema\Index;
use Archetype\Vendor\Doctrine\DBAL\Schema\Schema;
use Archetype\Vendor\Doctrine\DBAL\Schema\SchemaException;
use Archetype\Vendor\Doctrine\DBAL\Schema\Sequence;
use Archetype\Vendor\Doctrine\DBAL\Schema\Table;
/**
 * Schema Visitor used for Validation or Generation purposes.
 *
 * @deprecated
 */
interface Visitor
{
    /**
     * @return void
     *
     * @throws SchemaException
     */
    public function acceptSchema(Schema $schema);
    /** @return void */
    public function acceptTable(Table $table);
    /** @return void */
    public function acceptColumn(Table $table, Column $column);
    /**
     * @return void
     *
     * @throws SchemaException
     */
    public function acceptForeignKey(Table $localTable, ForeignKeyConstraint $fkConstraint);
    /** @return void */
    public function acceptIndex(Table $table, Index $index);
    /** @return void */
    public function acceptSequence(Sequence $sequence);
}
