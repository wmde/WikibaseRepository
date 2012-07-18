<?php

namespace Wikibase;

/**
 * Content object for articles representing Wikibase queries.
 *
 * @since 0.1
 *
 * @file
 * @ingroup Wikibase
 * @ingroup Content
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class QueryContent extends EntityContent {

	/**
	 * @since 0.1
	 * @var Query
	 */
	protected $query;

	/**
	 * Constructor.
	 * Do not use to construct new stuff from outside of this class, use the static newFoobar methods.
	 * In other words: treat as protected (which it was, but now cannot be since we derive from Content).
	 * @protected
	 *
	 * @since 0.1
	 *
	 * @param Query $query
	 */
	public function __construct( Query $query ) {
		parent::__construct( CONTENT_MODEL_WIKIBASE_ITEM );

		$this->query = $query;
	}

	/**
	 * Create a new queryContent object for the provided query.
	 *
	 * @since 0.1
	 *
	 * @param Query $query
	 *
	 * @return QueryContent
	 */
	public static function newFromQuery( Query $query ) {
		return new static( $query );
	}

	/**
	 * Create a new QueryContent object from the provided Query data.
	 *
	 * @since 0.1
	 *
	 * @param array $data
	 *
	 * @return QueryContent
	 */
	public static function newFromArray( array $data ) {
		return new static( new QueryObject( $data ) );
	}

	/**
	 * Gets the query that makes up this query content.
	 *
	 * @since 0.1
	 *
	 * @return Query
	 */
	public function getQuery() {
		return $this->query;
	}

	/**
	 * Sets the query that makes up this query content.
	 *
	 * @since 0.1
	 *
	 * @param Query $query
	 */
	public function setQuery( Query $query ) {
		$this->query = $query;
	}

	/**
	 * Returns a new empty QueryContent.
	 *
	 * @since 0.1
	 *
	 * @return QueryContent
	 */
	public static function newEmpty() {
		return new static( QueryObject::newEmpty() );
	}

	/**
	 * @see EntityContent::getEntity
	 *
	 * @since 0.1
	 *
	 * @return Query
	 */
	public function getEntity() {
		return $this->query;
	}

}
