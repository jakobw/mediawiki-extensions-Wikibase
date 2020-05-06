import Entity from '@/datamodel/Entity';
import EntityRevision from '@/datamodel/EntityRevision';
import { Store } from 'vuex';
import Application from '@/store/Application';
import { EntityState } from '@/store/entity';
import { Actions, Context, Getters } from 'vuex-smart-module';
import { EntityMutations } from '@/store/entity/mutations';
import { statementModule } from '@/store/statements';
import { rootModule } from '@/store';
import StatementMap from '@/datamodel/StatementMap';
import SavingError from '@/data-access/error/SavingError';

export class EntityActions extends Actions<EntityState, Getters<EntityState>, EntityMutations, EntityActions> {
	private store!: Store<Application>;
	private statementsModule!: Context<typeof statementModule>;
	private rootModule!: Context<typeof rootModule>;

	public $init( store: Store<Application> ): void {
		this.store = store;
		this.rootModule = rootModule.context( store );
		this.statementsModule = statementModule.context( store );
	}

	public entityInit(
		payload: { entity: string },
	): Promise<void> {
		return this.store.$services.get( 'readingEntityRepository' )
			.getEntity( payload.entity )
			.then( ( entityRevision: EntityRevision ) => this.dispatch( 'entityWrite', entityRevision ) );
	}

	public entitySave(
		payload: { statements: StatementMap; assertUser?: boolean },
	): Promise<void> {
		const entityId = this.state.id;
		const entity = new Entity( entityId, payload.statements );
		const base = new EntityRevision(
			new Entity( entityId, this.statementsModule.state[ entityId ] ),
			this.state.baseRevision,
		);

		return this.store.$services.get( 'writingEntityRepository' )
			.saveEntity( entity, base, payload.assertUser )
			.then(
				( entityRevision: EntityRevision ) => this.dispatch( 'entityWrite', entityRevision ),
				( error: Error ): never => {
					if ( error instanceof SavingError ) {
						this.rootModule.commit( 'addApplicationErrors', error.errors );
						throw new Error( 'saving failed' );
					}
					throw error;
				},
			);
	}

	public entityWrite(
		entityRevision: EntityRevision,
	): Promise<void> {
		this.commit( 'updateRevision', entityRevision.revisionId );
		this.commit( 'updateEntity', entityRevision.entity );

		return this.statementsModule.dispatch( 'initStatements', {
			entityId: entityRevision.entity.id,
			statements: entityRevision.entity.statements,
		} );
	}
}
