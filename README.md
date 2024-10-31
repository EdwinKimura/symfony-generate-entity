# symfony-generate-entity
Maps and creates Entities according to the database tables

I created this command with the purpose of simulating the doctrine:mapping:import command, mapping and creating the necessary Entities from the tables that exist in the database.

Bundles
- symfony/maker-bundle
- doctrine/dbal
- doctrine/orm
- doctrine/doctrine-bundle
- doctrine/doctrine-migrations-bundle

Create a new command:
symfony console make:command

Name the new command, and it will be created in the /src/Command folder

After this step, just copy the code to this generated file.
