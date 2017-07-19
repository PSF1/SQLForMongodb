This library only convert MySQL queries to MongoDB languaje. It's the first step to get compatibility betwen tradicional PHP solutions with MongoDB without change de bissnes logic.

A second goal is the hability to build a Query API in cluster form, balancing load betwen many servers. And at time, dividing the MongoDB load with a relation one to one (SQLForMongo node <--> MongoDB node)

Implemented:

* SELECT: Allow complex WHERE logic, ORDER BY, LIMIT and COUNT.

Roadmap:

* CREATE
* INSERT
* UPDATE
* DELETE
* LEFT JOIN
* UNION