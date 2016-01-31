#include "Tron.h"

int main() {
	// Execute loop forever (or until game ends)
	while (true) {
		/* Get an integer map of the field. Each int
		 * can either be Tron.Tile.EMPTY, Tron.Tile.PLAYER1, 
		 * Tron.Tile.PLAYER2, Tron.Tile.TAKEN_BY_PLAYER1, or
		 * Tron.Tile.TAKEN_BY_PLAYER2.   */
		std::vector< std::vector<int> > gameMap = getMap();
		/* Send your move.  This can be Tron.Direction.NORTH,
		 * Tron.Direction.SOUTH, Tron.Direction.EAST, or
		 * Tron.Direction.WEST.          */
		sendMove(NORTH);
	}
}	